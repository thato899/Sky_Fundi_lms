<?php

declare(strict_types=1);

namespace Modules\Academics\Application;

use Core\AuditLogs\Application\AuditLogService;
use Core\Support\Exceptions\DomainException;
use Modules\Academics\Events\ClassCreated;
use Modules\Academics\Infrastructure\Models\ClassGroup;

final class ClassService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function create(array $attributes): ClassGroup
    {
        $class = ClassGroup::create($attributes);

        event(new ClassCreated($class));

        return $class;
    }

    public function update(ClassGroup $class, array $attributes): ClassGroup
    {
        $before = $class->only(array_keys($attributes));
        $class->update($attributes);

        $this->auditLog->record(action: 'academics.class.updated', target: $class, before: $before, after: $attributes);

        return $class->fresh();
    }

    /**
     * Enrolment/capacity tracking itself belongs to a future module
     * (Attendance/Homeroom concerns are explicitly out of scope here —
     * see modules/Academics/README.md). This only validates that a
     * proposed headcount doesn't exceed the class's own capacity
     * field, for callers that already know a count.
     *
     * @throws DomainException
     */
    public function assertWithinCapacity(ClassGroup $class, int $proposedHeadcount): void
    {
        if ($class->capacity !== null && $proposedHeadcount > $class->capacity) {
            throw new DomainException("Class \"{$class->name}\" capacity is {$class->capacity}; {$proposedHeadcount} exceeds it.");
        }
    }
}
