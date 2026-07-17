<?php

declare(strict_types=1);

namespace Modules\Learners\Application;

use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Modules\Learners\Domain\Enums\GuardianStatus;
use Modules\Learners\Domain\Enums\LearnerStatus;
use Modules\Learners\Infrastructure\Models\GuardianProfile;
use Modules\Learners\Infrastructure\Models\LearnerGuardianRelationship;
use Modules\Learners\Infrastructure\Models\LearnerProfile;

final class GuardianPortalAccessService
{
    public function allows(User $user, LearnerProfile $learner): bool
    {
        if ($learner->getAttribute('deleted_at') !== null || $learner->getAttribute('archived_at') !== null) {
            return false;
        }

        $status = $learner->getAttribute('learner_status');
        if (! in_array($status, [LearnerStatus::Admitted, LearnerStatus::Active, LearnerStatus::TemporarilyInactive, LearnerStatus::Suspended], true)) {
            return false;
        }

        return $this->relationships($user, $learner)->exists();
    }

    /** @return Builder<LearnerGuardianRelationship> */
    public function relationships(User $user, LearnerProfile $learner): Builder
    {
        /** @var Builder<LearnerGuardianRelationship> $query */
        $query = LearnerGuardianRelationship::query();
        $query
            ->where('learner_profile_id', $learner->getKey())
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereRaw('(effective_from is null or effective_from <= ?)', [today()->toDateString()])
            ->whereRaw('(effective_until is null or effective_until >= ?)', [today()->toDateString()])
            ->whereIn('guardian_profile_id', GuardianProfile::query()->select('id')
                ->where('user_id', $user->getKey())
                ->where('status', GuardianStatus::Active->value)
                ->whereNull('archived_at')
                ->whereNull('deleted_at'));

        return $query;
    }
}
