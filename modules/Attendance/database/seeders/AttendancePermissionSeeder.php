<?php

declare(strict_types=1);

namespace Modules\Attendance\Database\Seeders;

use Core\RBAC\Application\RoleService;
use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Illuminate\Database\Seeder;

final class AttendancePermissionSeeder extends Seeder
{
    public const PERMISSIONS = ['attendance.view', 'attendance.create', 'attendance.record', 'attendance.update', 'attendance.finalize', 'attendance.reopen', 'attendance.cancel', 'attendance.export', 'attendance.view_reports'];

    public function run(RoleService $roles): void
    {
        foreach (self::PERMISSIONS as $permission) {
            $roles->registerPermission($permission, 'attendance', str_replace(['attendance.', '_'], ['', ' '], ucfirst($permission)));
        }
        foreach (['Super Admin', 'Organization Administrator', 'Academic Administrator'] as $name) {
            $this->grant($name, self::PERMISSIONS, $name === 'Super Admin');
        }
        foreach (['Teacher', 'Tutor'] as $name) {
            $this->grant($name, array_values(array_diff(self::PERMISSIONS, ['attendance.reopen', 'attendance.cancel', 'attendance.view_reports'])));
        }
    }

    private function grant(string $name, array $permissions, bool $system = false): void
    {
        $role = Role::query()->firstOrCreate(['name' => $name], ['is_system' => $system]);
        $role->permissions()->syncWithoutDetaching(Permission::query()->whereIn('name', $permissions)->pluck('id'));
    }
}
