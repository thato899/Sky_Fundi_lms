<?php

declare(strict_types=1);

namespace Modules\Academics\Application;

use Core\AuditLogs\Application\AuditLogService;
use Modules\Academics\Events\CurriculumAssigned;
use Modules\Academics\Events\SubjectCreated;
use Modules\Academics\Infrastructure\Models\Curriculum;
use Modules\Academics\Infrastructure\Models\Department;
use Modules\Academics\Infrastructure\Models\Subject;

final class SubjectService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function create(array $attributes): Subject
    {
        $subject = Subject::create($attributes);

        event(new SubjectCreated($subject));

        return $subject;
    }

    public function update(Subject $subject, array $attributes): Subject
    {
        $before = $subject->only(array_keys($attributes));
        $subject->update($attributes);

        $this->auditLog->record(action: 'academics.subject.updated', target: $subject, before: $before, after: $attributes);

        return $subject->fresh();
    }

    public function assignCurriculum(Subject $subject, Curriculum $curriculum): Subject
    {
        $subject->update(['curriculum_id' => $curriculum->id]);

        event(new CurriculumAssigned($curriculum, $subject));

        return $subject->fresh();
    }

    public function assignDepartment(Subject $subject, Department $department): Subject
    {
        $before = ['department_id' => $subject->department_id];
        $subject->update(['department_id' => $department->id]);

        $this->auditLog->record(
            action: 'academics.subject.department_assigned',
            target: $subject,
            before: $before,
            after: ['department_id' => $department->id],
        );

        return $subject->fresh();
    }
}
