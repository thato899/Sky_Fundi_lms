<?php

declare(strict_types=1);

namespace Modules\Learners\Application;

use Core\AuditLogs\Application\AuditLogService;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use DateTimeInterface;
use Modules\Learners\Infrastructure\Models\LearnerEnrolment;
use Modules\Learners\Infrastructure\Models\LearnerProfile;

final class LearnerEnrolmentService
{
    private const PLACEMENT_MAP = [
        'current_academic_year_id' => 'academic_year_id',
        'current_grade_id' => 'grade_id',
        'current_class_id' => 'class_id',
        'curriculum_id' => 'curriculum_id',
    ];

    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * Reconcile the enrolment timeline with the learner's saved placement.
     * Must run inside the caller's placement transaction. When no open row
     * exists but a previous placement is supplied, that superseded placement
     * is recorded as a closed row so timelines stay complete for learners
     * created before enrolment tracking existed.
     *
     * @param  array<string, string|null>|null  $previousPlacement  keyed by learner_profiles column names
     */
    public function syncFromPlacement(LearnerProfile $learner, ?User $actor = null, ?array $previousPlacement = null): void
    {
        $current = $this->tupleFromProfile($learner);
        /** @var LearnerEnrolment|null $open */
        $open = LearnerEnrolment::query()
            ->where('learner_profile_id', $learner->getKey())
            ->whereNull('ended_on')
            ->lockForUpdate()
            ->first();

        if ($open !== null && $this->matches($open, $current)) {
            return;
        }

        $today = now()->toDateString();
        if ($open !== null) {
            $open->setAttribute('ended_on', $today);
            $open->save();
        } else {
            $previous = $this->tupleFromColumns($previousPlacement ?? []);
            if (array_filter($previous) !== []) {
                LearnerEnrolment::query()->create([
                    ...$previous,
                    'organization_id' => $learner->getAttribute('organization_id'),
                    'learner_profile_id' => $learner->getKey(),
                    'started_on' => $this->fallbackStart($learner),
                    'ended_on' => $today,
                ]);
            }
        }

        if (array_filter($current) === []) {
            return;
        }

        LearnerEnrolment::query()->create([
            ...$current,
            'organization_id' => $learner->getAttribute('organization_id'),
            'learner_profile_id' => $learner->getKey(),
            'started_on' => $today,
            'actor_id' => $actor?->getKey(),
        ]);
    }

    /**
     * Class identifiers the learner occupied at any point in the inclusive
     * date window, oldest first.
     *
     * @return list<string>
     */
    public function classIdsDuring(LearnerProfile $learner, DateTimeInterface|string $start, DateTimeInterface|string $end): array
    {
        return LearnerEnrolment::query()
            ->where('organization_id', $learner->getAttribute('organization_id'))
            ->where('learner_profile_id', $learner->getKey())
            ->whereNotNull('class_id')
            ->whereDate('started_on', '<=', $end)
            ->where(fn ($query) => $query->whereNull('ended_on')->orWhereDate('ended_on', '>=', $start))
            ->orderBy('started_on')
            ->pluck('class_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Correct a historical enrolment record — for fixing data-entry errors
     * in the timeline, not for routine placement changes (those flow
     * through syncFromPlacement). Rejects a corrected range that would
     * overlap another enrolment row for the same learner and audits the
     * before/after state.
     *
     * @param  array<string, string|null>  $data  any of academic_year_id, grade_id, class_id, curriculum_id, started_on, ended_on
     */
    public function correct(LearnerEnrolment $enrolment, User $actor, array $data): LearnerEnrolment
    {
        $before = $enrolment->only(['academic_year_id', 'grade_id', 'class_id', 'curriculum_id', 'started_on', 'ended_on']);

        $startedOn = array_key_exists('started_on', $data) && $data['started_on'] !== null
            ? $data['started_on']
            : $enrolment->getAttribute('started_on')?->toDateString();
        $endedOn = array_key_exists('ended_on', $data) ? $data['ended_on'] : $enrolment->getAttribute('ended_on')?->toDateString();

        if ($startedOn === null) {
            throw new DomainException('A start date is required.');
        }
        if ($endedOn !== null && $endedOn < $startedOn) {
            throw new DomainException('The end date cannot be before the start date.');
        }

        $overlaps = LearnerEnrolment::query()
            ->where('learner_profile_id', $enrolment->getAttribute('learner_profile_id'))
            ->where('id', '!=', $enrolment->getKey())
            ->where(fn ($query) => $query->whereNull('ended_on')->orWhereDate('ended_on', '>=', $startedOn))
            ->when($endedOn !== null, fn ($query) => $query->whereDate('started_on', '<=', $endedOn))
            ->exists();
        if ($overlaps) {
            throw new DomainException('The corrected date range overlaps another enrolment record for this learner.');
        }

        foreach (['academic_year_id', 'grade_id', 'class_id', 'curriculum_id'] as $column) {
            if (array_key_exists($column, $data)) {
                $enrolment->setAttribute($column, $data[$column]);
            }
        }
        $enrolment->setAttribute('started_on', $startedOn);
        $enrolment->setAttribute('ended_on', $endedOn);
        $enrolment->setAttribute('actor_id', $actor->getKey());
        $enrolment->save();

        $this->audit->record(
            'learners.enrolment_corrected',
            $enrolment,
            before: $before,
            after: $enrolment->refresh()->only(['academic_year_id', 'grade_id', 'class_id', 'curriculum_id', 'started_on', 'ended_on']),
        );

        return $enrolment;
    }

    /** @return array<string, string|null> */
    private function tupleFromProfile(LearnerProfile $learner): array
    {
        $tuple = [];
        foreach (self::PLACEMENT_MAP as $profileColumn => $enrolmentColumn) {
            $tuple[$enrolmentColumn] = $learner->getAttribute($profileColumn);
        }

        return $tuple;
    }

    /**
     * @param  array<string, string|null>  $placement
     * @return array<string, string|null>
     */
    private function tupleFromColumns(array $placement): array
    {
        $tuple = [];
        foreach (self::PLACEMENT_MAP as $profileColumn => $enrolmentColumn) {
            $tuple[$enrolmentColumn] = $placement[$profileColumn] ?? null;
        }

        return $tuple;
    }

    /** @param  array<string, string|null>  $tuple */
    private function matches(LearnerEnrolment $enrolment, array $tuple): bool
    {
        foreach ($tuple as $column => $value) {
            if ($enrolment->getAttribute($column) !== $value) {
                return false;
            }
        }

        return true;
    }

    private function fallbackStart(LearnerProfile $learner): string
    {
        return $learner->getAttribute('admission_date')?->toDateString()
            ?? $learner->getAttribute('created_at')?->toDateString()
            ?? now()->toDateString();
    }
}
