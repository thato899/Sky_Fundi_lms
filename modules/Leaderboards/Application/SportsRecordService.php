<?php

declare(strict_types=1);

namespace Modules\Leaderboards\Application;

use Core\AuditLogs\Application\AuditLogService;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Modules\Leaderboards\Infrastructure\Models\SportsRecord;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;

final class SportsRecordService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * @param  array{learner_profile_id: string, activity: string, points: float|string, event_date: string, notes?: string|null}  $data
     */
    public function record(Organization $organization, User $actor, array $data): SportsRecord
    {
        $learner = LearnerProfile::query()->where('organization_id', $organization->getKey())->find($data['learner_profile_id'] ?? null);
        if (! $learner instanceof LearnerProfile) {
            throw new DomainException('The learner must belong to the active organization.');
        }
        if (trim((string) ($data['activity'] ?? '')) === '') {
            throw new DomainException('An activity name is required.');
        }
        if (! is_numeric($data['points'] ?? null)) {
            throw new DomainException('Points must be a number.');
        }

        $record = SportsRecord::query()->create([
            'organization_id' => $organization->getKey(),
            'learner_profile_id' => $learner->getKey(),
            'activity' => trim((string) $data['activity']),
            'points' => (float) $data['points'],
            'event_date' => $data['event_date'],
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'recorded_by' => $actor->getKey(),
        ]);
        $this->audit->record('sports_records.logged', $record, after: ['organization_id' => $organization->getKey(), 'learner_profile_id' => $learner->getKey(), 'activity' => $record->activity, 'points' => $record->points]);

        return $record;
    }

    public function delete(SportsRecord $record): void
    {
        $record->delete();
        $this->audit->record('sports_records.deleted', $record, after: ['organization_id' => $record->organization_id]);
    }
}
