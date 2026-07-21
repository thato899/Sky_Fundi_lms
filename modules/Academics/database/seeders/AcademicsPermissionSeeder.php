<?php

declare(strict_types=1);

namespace Modules\Academics\Database\Seeders;

use Core\RBAC\Application\RoleService;
use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Registers this module's permissions (see module.json's
 * "provides.permissions") through Core\RBAC and grants them to the
 * administrative roles, mirroring the other module permission seeders.
 * Called by Database\Seeders\DatabaseSeeder; also runnable standalone:
 *
 *   php artisan db:seed --class="Modules\Academics\Database\Seeders\AcademicsPermissionSeeder"
 *
 * Idempotent — safe to re-run.
 */
final class AcademicsPermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
        'academics.academic-years.view' => 'View academic years',
        'academics.academic-years.manage' => 'Create, update, close, and archive academic years',
        'academics.terms.view' => 'View academic terms',
        'academics.terms.manage' => 'Create and update academic terms',
        'academics.grades.view' => 'View grades',
        'academics.grades.manage' => 'Create, update, and reorder grades',
        'academics.classes.view' => 'View classes',
        'academics.classes.manage' => 'Create and update classes',
        'academics.subjects.view' => 'View subjects',
        'academics.subjects.manage' => 'Create and update subjects',
        'academics.departments.view' => 'View departments',
        'academics.departments.manage' => 'Create and update departments',
        'academics.curriculum.view' => 'View curricula',
        'academics.curriculum.manage' => 'Create, update, and assign curricula',
        'academics.calendar.view' => 'View the academic calendar',
        'academics.calendar.manage' => 'Manage academic calendar entries',
        'academics.timetable.view' => 'View timetable periods',
        'academics.timetable.manage' => 'Manage timetable periods',
    ];

    public function run(RoleService $roles): void
    {
        foreach (self::PERMISSIONS as $name => $description) {
            $roles->registerPermission($name, module: 'academics', description: $description);
        }
        foreach (['Super Admin', 'Organization Administrator', 'Academic Administrator'] as $name) {
            $role = Role::query()->firstOrCreate(['name' => $name], ['is_system' => $name === 'Super Admin']);
            $role->permissions()->syncWithoutDetaching(Permission::query()->whereIn('name', array_keys(self::PERMISSIONS))->pluck('id'));
        }
    }
}
