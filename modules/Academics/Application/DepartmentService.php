<?php

declare(strict_types=1);

namespace Modules\Academics\Application;

use Core\AuditLogs\Application\AuditLogService;
use Illuminate\Support\Collection;
use Modules\Academics\Infrastructure\Models\Department;

/**
 * Database-driven, per modules/Academics/README.md#departments —
 * Science/Mathematics/Languages/etc. are seed examples
 * (database/seeders/DepartmentSeeder.php), not a fixed list.
 */
final class DepartmentService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function create(array $attributes): Department
    {
        $department = Department::create($attributes);

        $this->auditLog->record(action: 'academics.department.created', target: $department, after: $attributes);

        return $department;
    }

    public function update(Department $department, array $attributes): Department
    {
        $before = $department->only(array_keys($attributes));
        $department->update($attributes);

        $this->auditLog->record(action: 'academics.department.updated', target: $department, before: $before, after: $attributes);

        return $department->fresh();
    }

    public function active(): Collection
    {
        return Department::query()->where('is_active', true)->orderBy('name')->get();
    }
}
