<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Controllers\Web;

use Core\Auth\Application\AuthService;
use Core\Branding\Application\BrandingService;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Learners\Application\GuardianInvitationService;
use Modules\Learners\Http\Requests\AcceptGuardianInvitationRequest;
use Modules\Learners\Http\Requests\StoreGuardianInvitationRequest;
use Modules\Learners\Infrastructure\Models\GuardianProfile;

final class GuardianInvitationController
{
    public function __construct(
        private readonly GuardianInvitationService $invitations,
        private readonly AuthService $auth,
        private readonly BrandingService $branding,
    ) {}

    public function store(StoreGuardianInvitationRequest $request, mixed $guardian): RedirectResponse
    {
        $guardian = $this->guardian($guardian);
        Gate::authorize('invite', $guardian);
        $this->invitations->invite($guardian, $this->actor($request), (string) $request->validated('email'));

        return back()->with('status', 'Guardian invitation sent.');
    }

    public function resend(Request $request, mixed $guardian, string $invitation): RedirectResponse
    {
        $guardian = $this->guardian($guardian);
        Gate::authorize('invite', $guardian);
        $this->invitations->resend($this->membership($guardian, $invitation), $guardian, $this->actor($request));

        return back()->with('status', 'Guardian invitation resent.');
    }

    public function revoke(Request $request, mixed $guardian, string $invitation): RedirectResponse
    {
        $guardian = $this->guardian($guardian);
        Gate::authorize('revokeInvitation', $guardian);
        $this->invitations->revoke($this->membership($guardian, $invitation), $guardian);

        return back()->with('status', 'Guardian invitation revoked.');
    }

    public function show(string $token): View
    {
        try {
            $membership = $this->invitations->resolve($token);
            $organization = $membership->organization()->firstOrFail();
        } catch (DomainException) {
            return view('guardian-invitations.unavailable', ['branding' => $this->branding->current()]);
        }

        return view('guardian-invitations.show', [
            'branding' => $this->branding->current(),
            'organization' => $organization,
            'membership' => $membership,
            'token' => $token,
        ]);
    }

    public function accept(AcceptGuardianInvitationRequest $request, string $token): RedirectResponse
    {
        try {
            $membership = $this->invitations->resolve($token);
            $user = $request->user();
            if (! $user instanceof User && User::query()->whereRaw('lower(email) = ?', [strtolower((string) $membership->invited_email)])->exists()) {
                $user = $this->auth->authenticate(
                    (string) $membership->invited_email,
                    (string) $request->validated('password'),
                    $request->ip() ?? '0.0.0.0',
                );
                Auth::login($user);
                $request->session()->regenerate();
            }
            $data = $request->validated();
            if (! $user instanceof User && trim((string) ($data['name'] ?? '')) === '') {
                throw ValidationException::withMessages(['name' => ['Your name is required to create an account.']]);
            }
            if (! $user instanceof User && ($data['password_confirmation'] ?? null) !== $data['password']) {
                throw ValidationException::withMessages(['password' => ['The password confirmation does not match.']]);
            }
            $guardian = $this->invitations->accept($token, $user instanceof User ? $user : null, $data);
            if (! Auth::check()) {
                Auth::login($guardian->user);
                $request->session()->regenerate();
            }
            $request->session()->put('organization_id', $guardian->organization_id);

            return redirect()->route('guardians.show', $guardian->uuid)->with('status', 'Invitation accepted. Your guardian portal is ready.');
        } catch (DomainException) {
            return redirect()->route('guardian-invitations.unavailable');
        }
    }

    public function unavailable(): View
    {
        return view('guardian-invitations.unavailable', ['branding' => $this->branding->current()]);
    }

    private function membership(GuardianProfile $guardian, string $id): Membership
    {
        return Membership::query()->whereKey($id)
            ->where('organization_id', $guardian->organization_id)
            ->whereKey($guardian->organization_membership_id)
            ->firstOrFail();
    }

    private function guardian(mixed $guardian): GuardianProfile
    {
        abort_unless($guardian instanceof GuardianProfile, 404);

        return $guardian;
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}
