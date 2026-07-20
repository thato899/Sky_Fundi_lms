<?php

declare(strict_types=1);

namespace Modules\Learners\Application;

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
