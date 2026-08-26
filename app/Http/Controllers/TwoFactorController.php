<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\PostLoginDestinationResolver;
use Core\Auth\Application\TwoFactorAuthenticationService;
use Core\Branding\Application\BrandingService;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Web two-factor enrollment and self-service management.
 *
 * enroll()/confirm() serve two entry points with one implementation:
 * a voluntarily-authenticated user turning 2FA on from their account
 * settings, and a user mid-login whose organization enforces 2FA and
 * who hasn't enrolled yet (WebAuthController redirected them here
 * instead of completing Auth::login()). resolveUser() tells the two
 * apart. edit()/disable()/regenerateRecoveryCodes() only ever make
 * sense for an already-authenticated user and use $request->user()
 * directly.
 */
final class TwoFactorController
{
    public function __construct(
        private readonly TwoFactorAuthenticationService $twoFactor,
        private readonly PostLoginDestinationResolver $destinations,
        private readonly BrandingService $branding,
    ) {}

    public function edit(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('auth.two-factor-settings', ['enabled' => $user->hasTwoFactorEnabled(), 'branding' => $this->branding->current()]);
    }

    public function enroll(Request $request): View|RedirectResponse
    {
        $user = $this->resolveUser($request);
        if ($user === null) {
            return redirect()->route('login');
        }
        if ($user->getAttribute('two_factor_secret') === null) {
            $this->twoFactor->generateSecret($user);
            $user = $user->fresh();
        }

        return view('auth.two-factor-enroll', [
            'secret' => $user->getAttribute('two_factor_secret'),
            'provisioningUri' => $this->twoFactor->provisioningUri($user),
            'forced' => $request->session()->has('two_factor.pending_user_id'),
            'branding' => $this->branding->current(),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $user = $this->resolveUser($request);
        if ($user === null) {
            return redirect()->route('login');
        }

        try {
            $codes = $this->twoFactor->confirm($user, (string) $request->input('code', ''));
        } catch (DomainException $exception) {
            return back()->withErrors(['code' => $exception->getMessage()]);
        }

        $pendingLogin = $request->session()->has('two_factor.pending_user_id');
        $continueUrl = route('security.two-factor.edit');

        if ($pendingLogin) {
            $remember = (bool) $request->session()->pull('two_factor.remember', false);
            $request->session()->forget('two_factor.pending_user_id');
            Auth::login($user, $remember);
            $request->session()->regenerate();
            $continueUrl = $this->destinations->redirect($user, $request)->getTargetUrl();
        }

        return redirect()->route('two-factor.recovery-codes')
            ->with('two_factor_recovery_codes', $codes)
            ->with('two_factor_continue_url', $continueUrl);
    }

    public function recoveryCodes(Request $request): View|RedirectResponse
    {
        $codes = $request->session()->get('two_factor_recovery_codes');
        if (! is_array($codes)) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-recovery-codes', [
            'codes' => $codes,
            'continueUrl' => $request->session()->get('two_factor_continue_url', route('dashboard')),
            'branding' => $this->branding->current(),
        ]);
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        try {
            $codes = $this->twoFactor->regenerateRecoveryCodes($user);
        } catch (DomainException $exception) {
            return back()->withErrors(['code' => $exception->getMessage()]);
        }

        return redirect()->route('two-factor.recovery-codes')
            ->with('two_factor_recovery_codes', $codes)
            ->with('two_factor_continue_url', route('security.two-factor.edit'));
    }

    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $request->validate(['password' => ['required', 'string']]);
        if (! Hash::check((string) $request->input('password'), (string) $user->getAuthPassword())) {
            return back()->withErrors(['password' => 'The provided password is incorrect.']);
        }

        $this->twoFactor->disable($user);

        return redirect()->route('security.two-factor.edit')->with('status', 'Two-factor authentication has been disabled.');
    }

    private function resolveUser(Request $request): ?User
    {
        $user = $request->user();
        if ($user instanceof User) {
            return $user;
        }
        $pendingId = $request->session()->get('two_factor.pending_user_id');

        return $pendingId !== null ? User::query()->find($pendingId) : null;
    }
}
