<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\PostLoginDestinationResolver;
use Core\Auth\Application\TwoFactorAuthenticationService;
use Core\Branding\Application\BrandingService;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Mid-login second-factor challenge for a user who already has 2FA
 * enabled (as opposed to TwoFactorController::enroll(), for a user who
 * doesn't yet). Operates entirely on the pending-login session state
 * WebAuthController sets — never on $request->user(), since the user
 * is not Auth::login()'d yet.
 */
final class TwoFactorChallengeController
{
    public function __construct(
        private readonly TwoFactorAuthenticationService $twoFactor,
        private readonly PostLoginDestinationResolver $destinations,
        private readonly BrandingService $branding,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        return $request->session()->has('two_factor.pending_user_id')
            ? view('auth.two-factor-challenge', ['branding' => $this->branding->current()])
            : redirect()->route('login');
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('two_factor.pending_user_id');
        $user = $userId !== null ? User::query()->find($userId) : null;
        if ($user === null) {
            $request->session()->forget(['two_factor.pending_user_id', 'two_factor.remember']);

            return redirect()->route('login');
        }

        $code = (string) $request->input('code', '');
        $recoveryCode = (string) $request->input('recovery_code', '');
        $verified = ($code !== '' && $this->twoFactor->verify($user, $code))
            || ($recoveryCode !== '' && $this->twoFactor->verifyRecoveryCode($user, $recoveryCode));

        if (! $verified) {
            return back()->withErrors(['code' => 'The provided code is invalid.']);
        }

        $remember = (bool) $request->session()->pull('two_factor.remember', false);
        $request->session()->forget('two_factor.pending_user_id');
        Auth::login($user, $remember);
        $request->session()->regenerate();

        return $this->destinations->redirect($user, $request);
    }
}
