<?php

declare(strict_types=1);

namespace Modules\Academics\Application;

use Core\AuditLogs\Application\AuditLogService;
use Modules\Academics\Domain\Enums\AcademicYearStatus;
use Modules\Academics\Events\AcademicYearClosed;
use Modules\Academics\Events\AcademicYearCreated;
use Modules\Academics\Infrastructure\Models\AcademicYear;

/**
 * Owns the "exactly one Current academic year" invariant — see
 * modules/Academics/README.md#academic-year. setCurrent() is the only
 * path that should ever set is_current=true; direct model updates
 * bypass this guarantee and are avoided elsewhere in the module.
 */
final class AcademicYearService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function create(array $attributes): AcademicYear
    {
        $year = AcademicYear::create([
            ...$attributes,
            'status' => $attributes['status'] ?? AcademicYearStatus::Upcoming,
            'is_current' => false,
        ]);

        event(new AcademicYearCreated($year));

        return $year;
    }

    public function update(AcademicYear $year, array $attributes): AcademicYear
    {
        $before = $year->only(array_keys($attributes));
        $year->update($attributes);

        $this->auditLog->record(action: 'academics.academic_year.updated', target: $year, before: $before, after: $attributes);

        return $year->fresh();
    }

    /**
     * Demotes any other Current year to Closed, then promotes the
     * given year — guaranteeing at most one Current year at a time.
     */
    public function setCurrent(AcademicYear $year): AcademicYear
    {
        AcademicYear::query()
            ->where('is_current', true)
            ->where('id', '!=', $year->id)
            ->each(function (AcademicYear $previous): void {
                $previous->update(['is_current' => false, 'status' => AcademicYearStatus::Closed]);
            });

        $year->update(['is_current' => true, 'status' => AcademicYearStatus::Current]);

        $this->auditLog->record(action: 'academics.academic_year.set_current', target: $year);

        return $year->fresh();
    }

    public function close(AcademicYear $year): AcademicYear
    {
        $year->update(['status' => AcademicYearStatus::Closed, 'is_current' => false]);

        event(new AcademicYearClosed($year));

        return $year->fresh();
    }

    public function archive(AcademicYear $year): AcademicYear
    {
        $year->update(['status' => AcademicYearStatus::Archived, 'is_current' => false]);

        $this->auditLog->record(action: 'academics.academic_year.archived', target: $year);

        return $year->fresh();
    }

    public function current(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_current', true)->first();
    }
}
