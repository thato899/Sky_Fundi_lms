<?php

declare(strict_types=1);

namespace Database\Seeders;

use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Illuminate\Database\Seeder;

final class OrganizationDashboardPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permission = Permission::query()->firstOrCreate(
            ['name' => 'organization.dashboard.view'],
            ['module' => 'core', 'description' => 'View the active organization dashboard'],
        );

        foreach (['Organization Administrator', 'Academic Administrator'] as $name) {
            $role = Role::query()->firstOrCreate(
                ['name' => $name],
                ['description' => "{$name} access within an assigned organization.", 'is_system' => false],
            );
            $role->permissions()->syncWithoutDetaching([$permission->getKey()]);
        }
    }
}
