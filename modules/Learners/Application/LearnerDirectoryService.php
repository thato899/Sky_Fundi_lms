<?php

declare(strict_types=1);

namespace Modules\Learners\Application;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;

final class LearnerDirectoryService
{
    private const SORTS = [
        'learner_number' => 'learner_number',
        'first_name' => 'first_name',
        'last_name' => 'last_name',
        'admission_date' => 'admission_date',
        'learner_status' => 'learner_status',
        'created_date' => 'created_at',
    ];

    public function paginate(Organization $organization, array $filters): LengthAwarePaginator
    {
        $sort = (string) ($filters['sort'] ?? 'learner_number');
        $direction = (string) ($filters['direction'] ?? 'asc');

        return LearnerProfile::query()
            ->where('organization_id', $organization->getKey())
            ->with(['currentAcademicYear', 'currentGrade', 'currentClass', 'curriculum'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    foreach (['first_name', 'last_name', 'preferred_name', 'learner_number', 'admission_number'] as $column) {
                        $query->orWhere($column, 'like', '%'.$search.'%');
                    }
                });
            })
            ->when($filters['learner_status'] ?? null, fn (Builder $query, string $value) => $query->where('learner_status', $value))
            ->when($filters['onboarding_status'] ?? null, fn (Builder $query, string $value) => $query->where('onboarding_status', $value))
            ->when($filters['academic_year_id'] ?? null, fn (Builder $query, string $value) => $query->where('current_academic_year_id', $value))
            ->when($filters['curriculum_id'] ?? null, fn (Builder $query, string $value) => $query->where('curriculum_id', $value))
            ->when($filters['grade_id'] ?? null, fn (Builder $query, string $value) => $query->where('current_grade_id', $value))
            ->when($filters['class_id'] ?? null, fn (Builder $query, string $value) => $query->where('current_class_id', $value))
            ->when(array_key_exists('portal_access_enabled', $filters), fn (Builder $query) => $query->where('portal_access_enabled', $filters['portal_access_enabled']))
            ->when(($filters['archived'] ?? null) === true, fn (Builder $query) => $query->where('learner_status', 'archived'))
            ->when(($filters['archived'] ?? null) === false, fn (Builder $query) => $query->where('learner_status', '!=', 'archived'))
            ->when(! array_key_exists('archived', $filters), fn (Builder $query) => $query->where('learner_status', '!=', 'archived'))
            ->when($filters['admission_date_from'] ?? null, fn (Builder $query, string $value) => $query->whereDate('admission_date', '>=', $value))
            ->when($filters['admission_date_to'] ?? null, fn (Builder $query, string $value) => $query->whereDate('admission_date', '<=', $value))
            ->orderBy(self::SORTS[$sort], $direction)
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 25));
    }
}
