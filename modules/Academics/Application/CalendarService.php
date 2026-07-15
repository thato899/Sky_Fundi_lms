<?php

declare(strict_types=1);

namespace Modules\Academics\Application;

use Illuminate\Support\Collection;
use Modules\Academics\Domain\Enums\CalendarEntryType;
use Modules\Academics\Events\CalendarUpdated;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\CalendarEntry;

/**
 * A single entries table covers School Days, Public Holidays, Exam
 * Periods, Assessment Periods, and Events — see
 * modules/Academics/README.md#calendar and
 * Domain\Enums\CalendarEntryType.
 */
final class CalendarService
{
    public function addEntry(AcademicYear $year, array $attributes): CalendarEntry
    {
        /** @var CalendarEntry $entry */
        $entry = $year->calendarEntries()->create([...$attributes, 'organization_id' => $year->getAttribute('organization_id')]);

        event(new CalendarUpdated($entry, 'created'));

        return $entry;
    }

    public function updateEntry(CalendarEntry $entry, array $attributes): CalendarEntry
    {
        $entry->update($attributes);

        event(new CalendarUpdated($entry, 'updated'));

        return $entry->fresh();
    }

    public function removeEntry(CalendarEntry $entry): void
    {
        event(new CalendarUpdated($entry, 'deleted'));

        $entry->delete();
    }

    public function entriesOfType(AcademicYear $year, CalendarEntryType $type): Collection
    {
        return $year->calendarEntries()->where('type', $type)->orderBy('start_date')->get();
    }

    /**
     * Every entry overlapping the given date range, regardless of
     * type — the primary read path a future timetable/scheduling
     * module would use to know which days are unavailable.
     */
    public function entriesBetween(AcademicYear $year, \DateTimeInterface $from, \DateTimeInterface $to): Collection
    {
        return $year->calendarEntries()
            ->where('start_date', '<=', $to)
            ->where('end_date', '>=', $from)
            ->orderBy('start_date')
            ->get();
    }
}
