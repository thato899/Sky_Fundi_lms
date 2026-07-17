<?php

declare(strict_types=1);

namespace Modules\Learners\Policies;

use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Users\Infrastructure\Models\User;
use Modules\Learners\Application\GuardianPortalAccessService;
use Modules\Learners\Domain\Enums\LearnerStatus;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Domain\Enums\OrganizationStatus;

final class LearnerPolicy
{
    public function __construct(
        private readonly PermissionResolver $permissions,
        private readonly GuardianPortalAccessService $guardianAccess,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'learners.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'learners.create');
    }

    public function view(User $user, LearnerProfile $learner): bool
    {
        return $this->allows($user, 'learners.view', $learner) || $this->guardianAccess->allows($user, $learner);
    }

    public function update(User $user, LearnerProfile $learner): bool
    {
        return $this->allows($user, 'learners.update', $learner) && $this->status($learner) !== LearnerStatus::Archived;
    }

    public function manageAcademicProfile(User $user, LearnerProfile $learner): bool
    {
        return $this->allows($user, 'learners.manage_academic_profile', $learner) && $this->status($learner) !== LearnerStatus::Archived;
    }

    public function manageGuardians(User $user, LearnerProfile $learner): bool
    {
        return $this->allows($user, 'guardians.manage_relationships', $learner) && $this->status($learner) !== LearnerStatus::Archived;
    }

    public function manageStatus(User $user, LearnerProfile $learner): bool
    {
        return $this->allows($user, 'learners.manage_status', $learner) && $this->status($learner) !== LearnerStatus::Archived;
    }

    public function archive(User $user, LearnerProfile $learner): bool
    {
        return $this->allows($user, 'learners.archive', $learner) && $this->status($learner) !== LearnerStatus::Archived;
    }

    public function restore(User $user, LearnerProfile $learner): bool
    {
        return $this->allows($user, 'learners.restore', $learner) && $this->status($learner) === LearnerStatus::Archived;
    }

    public function viewStatusHistory(User $user, LearnerProfile $learner): bool
    {
        return $this->allows($user, 'learners.view_status_history', $learner);
    }

    public function overrideNumber(User $user): bool
    {
        return $this->allows($user, 'learners.override_number');
    }

    private function allows(User $user, string $permission, ?LearnerProfile $learner = null): bool
    {
        $membership = request()->attributes->get('organization_membership');
        if (! $membership instanceof Membership || $membership->getAttribute('user_id') !== $user->getKey()) {
            return false;
        }
        $organization = $membership->organization()->first();
        if ($organization === null || $organization->getAttribute('status') !== OrganizationStatus::Active) {
            return false;
        }
        if ($learner !== null && $learner->getAttribute('organization_id') !== $membership->getAttribute('organization_id')) {
            return false;
        }

        return $this->permissions->allows($membership, $permission);
    }

    private function status(LearnerProfile $learner): LearnerStatus
    {
        $status = $learner->getAttribute('learner_status');
        assert($status instanceof LearnerStatus);

        return $status;
    }
}
