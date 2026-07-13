<?php

declare(strict_types=1);

namespace Modules\Academics\Application;

use Core\AuditLogs\Application\AuditLogService;
use Modules\Academics\Events\CurriculumAssigned;
use Modules\Academics\Events\GradeCreated;
use Modules\Academics\Infrastructure\Models\Curriculum;
use Modules\Academics\Infrastructure\Models\Grade;

final class GradeService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function create(array $attributes): Grade
    {
        $grade = Grade::create($attributes);

        event(new GradeCreated($grade));

        return $grade;
    }

    public function update(Grade $grade, array $attributes): Grade
    {
        $before = $grade->only(array_keys($attributes));
        $grade->update($attributes);

        $this->auditLog->record(action: 'academics.grade.updated', target: $grade, before: $before, after: $attributes);

        return $grade->fresh();
    }

    public function assignCurriculum(Grade $grade, Curriculum $curriculum): Grade
    {
        $grade->update(['curriculum_id' => $curriculum->id]);

        event(new CurriculumAssigned($curriculum, $grade));

        return $grade->fresh();
    }

    public function reorder(array $orderedGradeIds): void
    {
        foreach ($orderedGradeIds as $position => $gradeId) {
            Grade::query()->whereKey($gradeId)->update(['order' => $position + 1]);
        }

        $this->auditLog->record(action: 'academics.grades.reordered', after: ['order' => $orderedGradeIds]);
    }
}
