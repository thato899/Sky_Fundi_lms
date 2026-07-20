<?php

declare(strict_types=1);

namespace App\Application;

use Core\Identity\Application\PermissionResolver;
use Core\Identity\Domain\Enums\MembershipStatus;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Users\Infrastructure\Models\User;
use Modules\Learners\Infrastructure\Models\GuardianProfile;
use Modules\Learners\Infrastructure\Models\LearnerProfile;

/**
 * Resolves the signed-in persona (learner, guardian, or staff) for the
 * active organization so post-login redirects and layout navigation stay
 * consistent. Learner and guardian detection mirrors portal rules: a
 * linked portal-enabled learner profile, or an active linked guardian.
 */
final class NavigationContext
{
    public function __construct(private readonly PermissionResolver $permissions) {}

    public function learnerFor(User $user, string $organizationId): ?LearnerProfile
    {
        return LearnerProfile::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $user->getKey())
            ->where('portal_access_enabled', true)
            ->first();
    }

    public function guardianFor(User $user, string $organizationId): ?GuardianProfile
    {
        /** @var GuardianProfile|null $guardian */
        $guardian = GuardianProfile::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $user->getKey())
            ->where('status', 'active')
            ->whereNull('archived_at')
            ->first();

        return $guardian;
    }

    /** @return array{persona: string|null, links: list<array{label: string, href: string, active: bool}>} */
    public function for(?User $user, ?string $organizationId): array
    {
        if ($user === null) {
            return ['persona' => null, 'links' => []];
        }

        if ($user->can('core.roles.manage')) {
            return ['persona' => 'Super admin', 'links' => [$this->link('Overview', route('super-admin.dashboard'))]];
        }

        if ($organizationId === null) {
            return ['persona' => null, 'links' => []];
        }

        if ($this->learnerFor($user, $organizationId) !== null) {
            return ['persona' => 'Learner', 'links' => [$this->link('My quizzes', route('quizzes.assigned'))]];
        }

        $guardian = $this->guardianFor($user, $organizationId);
        if ($guardian !== null) {
            return ['persona' => 'Guardian', 'links' => [
                $this->link('My learners', route('guardians.show', $guardian->getAttribute('uuid'))),
            ]];
        }

        $membership = $this->membershipFor($user, $organizationId);
        if ($membership === null) {
            return ['persona' => null, 'links' => []];
        }

        $links = [];
        if ($this->permissions->allows($membership, 'organization.dashboard.view')) {
            $links[] = $this->link('Dashboard', route('dashboard'));
        }
        if ($this->permissions->allows($membership, 'assessments.view')) {
            $links[] = $this->link('Assessments', route('assessments.index'));
        }
        $teaches = $this->permissions->allows($membership, 'quiz_submissions.mark');
        if ($teaches) {
            $links[] = $this->link('Interventions', route('quizzes.interventions'));
        }
        if ($this->permissions->allows($membership, 'learners.view')) {
            $links[] = $this->link('Learners', route('learners.index'));
        }
        if ($this->permissions->allows($membership, 'reports.view')) {
            $links[] = $this->link('Reports', route('reports.dashboard'));
        }
        if ($this->permissions->allows($membership, 'subscriptions.view')) {
            $links[] = $this->link('Subscription', route('subscription.dashboard'));
        }

        $manages = $this->permissions->allows($membership, 'learners.update');

        return ['persona' => $teaches && ! $manages ? 'Teacher' : 'Staff', 'links' => $links];
    }

    private function membershipFor(User $user, string $organizationId): ?Membership
    {
        /** @var Membership|null $membership */
        $membership = $user->memberships()
            ->where('organization_id', $organizationId)
            ->where('status', MembershipStatus::Active->value)
            ->first();

        return $membership;
    }

    /** @return array{label: string, href: string, active: bool} */
    private function link(string $label, string $href): array
    {
        return ['label' => $label, 'href' => $href, 'active' => url()->current() === $href];
    }
}
