<?php

declare(strict_types=1);

namespace Modules\Scheduling\Database\Seeders;

use Core\RBAC\Application\RoleService;
use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Illuminate\Database\Seeder;

final class SchedulingPermissionSeeder extends Seeder
{
    public const PERMISSIONS = ['scheduling.view', 'scheduling.manage_periods', 'scheduling.manage_calendar', 'scheduling.manage_rooms', 'scheduling.manage_templates', 'scheduling.manage_lessons', 'scheduling.assign_staff', 'scheduling.materialize', 'scheduling.reschedule', 'scheduling.cancel', 'scheduling.complete', 'scheduling.override_conflicts', 'scheduling.export', 'scheduling.create_attendance'];

    public function run(RoleService $roles): void
    {
        foreach (self::PERMISSIONS as $permission) {
            $roles->registerPermission($permission, 'scheduling', str_replace(['scheduling.', '_'], ['', ' '], ucfirst($permission)));
        }
        foreach (['Super Admin', 'Organization Administrator', 'Academic Administrator'] as $name) {
            $this->grant($name, self::PERMISSIONS, $name === 'Super Admin');
        }
        foreach (['Teacher', 'Tutor'] as $name) {
            $this->grant($name, ['scheduling.view', 'scheduling.complete', 'scheduling.create_attendance']);
        }
    }

    private function grant(string $name, array $permissions, bool $system = false): void
    {
        $role = Role::query()->firstOrCreate(['name' => $name], ['is_system' => $system]);
        $role->permissions()->syncWithoutDetaching(Permission::query()->whereIn('name', $permissions)->pluck('id'));
    }
}
