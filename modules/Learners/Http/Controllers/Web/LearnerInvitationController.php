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
use Modules\Learners\Application\LearnerInvitationService;
use Modules\Learners\Http\Requests\AcceptLearnerInvitationRequest;
use Modules\Learners\Http\Requests\StoreLearnerInvitationRequest;
use Modules\Learners\Infrastructure\Models\LearnerProfile;

final class LearnerInvitationController
{
    public function __construct(
        private readonly LearnerInvitationService $invitations,
        private readonly AuthService $auth,
        private readonly BrandingService $branding,
    ) {}

    public function store(StoreLearnerInvitationRequest $request, mixed $learner): RedirectResponse
    {
        $learner = $this->learner($learner);
        Gate::authorize('invite', $learner);
        $this->invitations->invite($learner, $this->actor($request), (string) $request->validated('email'));

        return back()->with('status', 'Learner invitation sent.');
    }

    public function resend(Request $request, mixed $learner, string $invitation): RedirectResponse
    {
        $learner = $this->learner($learner);
        Gate::authorize('invite', $learner);
        $this->invitations->resend($this->membership($learner, $invitation), $learner, $this->actor($request));

        return back()->with('status', 'Learner invitation resent.');
    }

    public function revoke(Request $request, mixed $learner, string $invitation): RedirectResponse
    {
        $learner = $this->learner($learner);
        Gate::authorize('revokeInvitation', $learner);
        $this->invitations->revoke($this->membership($learner, $invitation), $learner);

        return back()->with('status', 'Learner invitation revoked.');
    }

    public function show(string $token): View
    {
        try {
            $membership = $this->invitations->resolve($token);
            $organization = $membership->organization()->firstOrFail();
        } catch (DomainException) {
            return view('learner-invitations.unavailable', ['branding' => $this->branding->current()]);
        }

        return view('learner-invitations.show', [
            'branding' => $this->branding->current(),
            'organization' => $organization,
            'membership' => $membership,
            'token' => $token,
        ]);
    }

    public function accept(AcceptLearnerInvitationRequest $request, string $token): RedirectResponse
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
            $learner = $this->invitations->accept($token, $user instanceof User ? $user : null, $data);
            if (! Auth::check()) {
                Auth::login($learner->user);
                $request->session()->regenerate();
            }
            $request->session()->put('organization_id', $learner->organization_id);

            return redirect()->route('quizzes.assigned')->with('status', 'Invitation accepted. Your learner portal is ready.');
        } catch (DomainException) {
            return redirect()->route('learner-invitations.unavailable');
        }
    }

    public function unavailable(): View
    {
        return view('learner-invitations.unavailable', ['branding' => $this->branding->current()]);
    }

    private function membership(LearnerProfile $learner, string $id): Membership
    {
        return Membership::query()->whereKey($id)
            ->where('organization_id', $learner->organization_id)
            ->whereKey($learner->organization_membership_id)
            ->firstOrFail();
    }

    private function learner(mixed $learner): LearnerProfile
    {
        abort_unless($learner instanceof LearnerProfile, 404);

        return $learner;
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}
