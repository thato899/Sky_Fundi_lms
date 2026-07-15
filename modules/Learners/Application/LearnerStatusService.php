<?php

declare(strict_types=1);

namespace Modules\Learners\Application;

use Core\Identity\Infrastructure\Models\Membership;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Learners\Domain\Enums\LearnerStatus;
use Modules\Learners\Events\LearnerArchived;
use Modules\Learners\Events\LearnerRestored;
use Modules\Learners\Events\LearnerStatusChanged;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Learners\Infrastructure\Models\LearnerStatusHistory;

final class LearnerStatusService
{
    /** @var array<string, list<LearnerStatus>> */
    private const TRANSITIONS = [
        'pending' => [LearnerStatus::Admitted],
        'admitted' => [LearnerStatus::Active, LearnerStatus::Withdrawn],
        'active' => [LearnerStatus::TemporarilyInactive, LearnerStatus::Suspended, LearnerStatus::Withdrawn, LearnerStatus::Transferred, LearnerStatus::Completed],
        'temporarily_inactive' => [LearnerStatus::Active, LearnerStatus::Withdrawn],
        'suspended' => [LearnerStatus::Active, LearnerStatus::Withdrawn, LearnerStatus::Transferred],
        'withdrawn' => [],
        'transferred' => [],
        'completed' => [],
    ];

    /** @return list<LearnerStatus> */
    public function availableTransitions(LearnerProfile $learner): array
    {
        $status = $this->status($learner);

        return $status === LearnerStatus::Archived ? [] : self::TRANSITIONS[$status->value];
    }

    public function transition(LearnerProfile $learner, LearnerStatus $newStatus, User $actor, ?string $reason = null): LearnerProfile
    {
        if ($newStatus === LearnerStatus::Archived) {
            return $this->archive($learner, $actor, $reason);
        }

        [$result, $previousStatus] = $this->change($learner, $newStatus, $actor, $reason, false);
        event(new LearnerStatusChanged($result, $previousStatus, $newStatus, $actor, $reason));

        return $result;
    }

    public function archive(LearnerProfile $learner, User $actor, ?string $reason = null): LearnerProfile
    {
        [$result, $previousStatus] = $this->change($learner, LearnerStatus::Archived, $actor, $reason, true);
        event(new LearnerStatusChanged($result, $previousStatus, LearnerStatus::Archived, $actor, $reason));
        event(new LearnerArchived($result, $previousStatus, $actor, $reason));

        return $result;
    }

    public function restore(LearnerProfile $learner, User $actor, ?string $reason = null): LearnerProfile
    {
        $this->assertActorBelongsToOrganization($actor, $this->organizationId($learner));

        $result = DB::transaction(function () use ($learner, $actor, $reason): LearnerProfile {
            $locked = $this->lockedLearner($learner);
            if ($this->status($locked) !== LearnerStatus::Archived) {
                throw new DomainException('Only archived learners can be restored.');
            }

            $archiveHistory = LearnerStatusHistory::query()
                ->where('organization_id', $this->organizationId($locked))
                ->where('learner_profile_id', $locked->getKey())
                ->where('new_status', LearnerStatus::Archived->value)
                ->latest('changed_at')
                ->first();

            if ($archiveHistory === null || $this->previousStatus($archiveHistory) === LearnerStatus::Archived) {
                throw new DomainException('The learner has no previous non-archived status to restore.');
            }

            $restoredStatus = $this->previousStatus($archiveHistory);
            $locked->forceFill([
                'learner_status' => $restoredStatus,
                'archived_at' => null,
                'updated_by' => $actor->getKey(),
            ])->save();
            $this->recordHistory($locked, LearnerStatus::Archived, $restoredStatus, $actor, $reason);

            return $locked->refresh();
        }, 3);

        event(new LearnerStatusChanged($result, LearnerStatus::Archived, $this->status($result), $actor, $reason));
        event(new LearnerRestored($result, $this->status($result), $actor, $reason));

        return $result;
    }

    /** @return array{LearnerProfile, LearnerStatus} */
    private function change(LearnerProfile $learner, LearnerStatus $newStatus, User $actor, ?string $reason, bool $archiving): array
    {
        $this->assertActorBelongsToOrganization($actor, $this->organizationId($learner));

        return DB::transaction(function () use ($learner, $newStatus, $actor, $reason, $archiving): array {
            $locked = $this->lockedLearner($learner);
            $previousStatus = $this->status($locked);

            if ($previousStatus === $newStatus) {
                throw new DomainException('Learner status transitions must change the status.');
            }
            if ($previousStatus === LearnerStatus::Archived) {
                throw new DomainException('Archived learners must be restored before another status transition.');
            }
            if (! $archiving && ! in_array($newStatus, self::TRANSITIONS[$previousStatus->value], true)) {
                throw new DomainException("Invalid learner status transition from {$previousStatus->value} to {$newStatus->value}.");
            }

            $locked->forceFill([
                'learner_status' => $newStatus,
                'archived_at' => $archiving ? now() : null,
                'updated_by' => $actor->getKey(),
            ])->save();
            $this->recordHistory($locked, $previousStatus, $newStatus, $actor, $reason);

            return [$locked->refresh(), $previousStatus];
        }, 3);
    }

    private function lockedLearner(LearnerProfile $learner): LearnerProfile
    {
        /** @var LearnerProfile $locked */
        $locked = LearnerProfile::query()
            ->whereKey($learner->getKey())
            ->where('organization_id', $this->organizationId($learner))
            ->lockForUpdate()
            ->firstOrFail();

        return $locked;
    }

    private function recordHistory(LearnerProfile $learner, LearnerStatus $previous, LearnerStatus $new, User $actor, ?string $reason): void
    {
        LearnerStatusHistory::query()->create([
            'organization_id' => $this->organizationId($learner),
            'learner_profile_id' => $learner->getKey(),
            'previous_status' => $previous,
            'new_status' => $new,
            'actor_id' => $actor->getKey(),
            'reason' => $reason,
            'changed_at' => now(),
        ]);
    }

    private function assertActorBelongsToOrganization(User $actor, string $organizationId): void
    {
        if (! Membership::query()->where('user_id', $actor->getKey())->where('organization_id', $organizationId)->exists()) {
            throw new DomainException('The status-change actor must belong to the learner organization.');
        }
    }

    private function organizationId(LearnerProfile $learner): string
    {
        return (string) $learner->getAttribute('organization_id');
    }

    private function status(LearnerProfile $learner): LearnerStatus
    {
        $status = $learner->getAttribute('learner_status');
        assert($status instanceof LearnerStatus);

        return $status;
    }

    private function previousStatus(LearnerStatusHistory $history): LearnerStatus
    {
        $status = $history->getAttribute('previous_status');
        assert($status instanceof LearnerStatus);

        return $status;
    }
}
