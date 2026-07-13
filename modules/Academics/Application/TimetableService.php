<?php

declare(strict_types=1);

namespace Modules\Academics\Application;

use Core\AuditLogs\Application\AuditLogService;
use Illuminate\Support\Collection;
use Modules\Academics\Domain\Enums\DayOfWeek;
use Modules\Academics\Infrastructure\Models\TimetablePeriod;

/**
 * Reusable timetable building blocks only — days, periods, times,
 * breaks. No generation/scheduling algorithm and no assignment of a
 * class/subject/teacher onto a period exists here — see
 * modules/Academics/README.md#timetable-foundation ("No timetable
 * generation yet").
 */
final class TimetableService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function createPeriod(array $attributes): TimetablePeriod
    {
        $period = TimetablePeriod::create($attributes);

        $this->auditLog->record(action: 'academics.timetable.period_created', target: $period, after: $attributes);

        return $period;
    }

    public function updatePeriod(TimetablePeriod $period, array $attributes): TimetablePeriod
    {
        $before = $period->only(array_keys($attributes));
        $period->update($attributes);

        $this->auditLog->record(action: 'academics.timetable.period_updated', target: $period, before: $before, after: $attributes);

        return $period->fresh();
    }

    public function periodsForDay(DayOfWeek $day): Collection
    {
        return TimetablePeriod::query()
            ->where('day_of_week', $day)
            ->orderBy('order')
            ->get();
    }
}
