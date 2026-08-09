<?php

declare(strict_types=1);

namespace Modules\EnterpriseOperations\Database\Seeders;

use Core\RBAC\Application\RoleService;
use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Illuminate\Database\Seeder;

final class EnterpriseOperationsPermissionSeeder extends Seeder
{
    public const PERMISSIONS = ['enterprise_operations.view', 'enterprise_operations.manage', 'enterprise_operations.approve'];

    public function run(RoleService $roles): void
    {
        foreach (self::PERMISSIONS as $permission) { $roles->registerPermission($permission, 'enterprise-operations', str_replace(['enterprise_operations.', '_'], ['', ' '], ucfirst($permission))); }
        foreach (['Super Admin', 'Organization Administrator'] as $name) { $role = Role::query()->firstOrCreate(['name' => $name], ['is_system' => $name === 'Super Admin']); $role->permissions()->syncWithoutDetaching(Permission::query()->whereIn('name', self::PERMISSIONS)->pluck('id')); }
    }
}
