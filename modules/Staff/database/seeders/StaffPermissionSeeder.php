<?php

declare(strict_types=1);

namespace Modules\Staff\Database\Seeders;

use Core\RBAC\Application\RoleService;
use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Illuminate\Database\Seeder;

final class StaffPermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
        'staff.view', 'staff.create', 'staff.update', 'staff.invite', 'staff.activate', 'staff.suspend',
        'staff.archive', 'staff.restore', 'staff.manage_roles', 'staff.manage_employment', 'staff.view_audit', 'staff.manage_documents',
    ];

    public function run(RoleService $roles): void
    {
        foreach (self::PERMISSIONS as $permission) {
            $roles->registerPermission($permission, 'staff', str_replace(['staff.', '_'], ['', ' '], ucfirst($permission)));
        }
        $role = Role::query()->firstOrCreate(['name' => 'Organization Administrator'], ['description' => 'Organization administration', 'is_system' => false]);
        $role->permissions()->syncWithoutDetaching(Permission::query()->whereIn('name', self::PERMISSIONS)->pluck('id'));
    }
}
