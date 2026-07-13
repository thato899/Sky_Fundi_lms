<?php

declare(strict_types=1);

namespace Modules\Academics\Application;

use Core\AuditLogs\Application\AuditLogService;
use Modules\Academics\Domain\Enums\AcademicTermStatus;
use Modules\Academics\Events\AcademicTermCreated;
use Modules\Academics\Infrastructure\Models\AcademicTerm;
use Modules\Academics\Infrastructure\Models\AcademicYear;

/**
 * Mirrors AcademicYearService's "exactly one Current" invariant,
 * scoped per academic year rather than globally — a year in progress
 * has exactly one current term; other years' terms are unaffected.
 */
final class AcademicTermService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function create(AcademicYear $year, array $attributes): AcademicTerm
    {
        $term = $year->terms()->create([
            ...$attributes,
            'status' => $attributes['status'] ?? AcademicTermStatus::Upcoming,
            'is_current' => false,
        ]);

        event(new AcademicTermCreated($term));

        return $term;
    }

    public function update(AcademicTerm $term, array $attributes): AcademicTerm
    {
        $before = $term->only(array_keys($attributes));
        $term->update($attributes);

        $this->auditLog->record(action: 'academics.academic_term.updated', target: $term, before: $before, after: $attributes);

        return $term->fresh();
    }

    public function setCurrent(AcademicTerm $term): AcademicTerm
    {
        AcademicTerm::query()
            ->where('academic_year_id', $term->academic_year_id)
            ->where('is_current', true)
            ->where('id', '!=', $term->id)
            ->each(fn (AcademicTerm $previous) => $previous->update(['is_current' => false, 'status' => AcademicTermStatus::Closed]));

        $term->update(['is_current' => true, 'status' => AcademicTermStatus::Current]);

        $this->auditLog->record(action: 'academics.academic_term.set_current', target: $term);

        return $term->fresh();
    }

    public function currentFor(AcademicYear $year): ?AcademicTerm
    {
        return $year->terms()->where('is_current', true)->first();
    }
}
