<?php

declare(strict_types=1);

namespace Modules\Materials\Database\Seeders;

use Core\RBAC\Application\RoleService;
use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Illuminate\Database\Seeder;

final class MaterialsPermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
        'materials.view' => 'View uploaded course material and its chunking status',
        'materials.upload' => 'Upload course material for chunking and AI-drafted quiz generation',
        'materials.delete' => 'Delete uploaded course material',
    ];

    public function run(RoleService $roles): void
    {
        foreach (self::PERMISSIONS as $name => $description) {
            $roles->registerPermission($name, 'materials', $description);
        }

        $all = array_keys(self::PERMISSIONS);
        $this->grant('Super Admin', $all, true);
        $this->grant('Organization Administrator', $all);
        $this->grant('Academic Administrator', $all);
        $this->grant('Teacher', $all);
        $this->grant('Tutor', $all);
    }

    private function grant(string $roleName, array $permissions, bool $system = false): void
    {
        $role = Role::query()->firstOrCreate(['name' => $roleName], ['is_system' => $system]);
        $ids = Permission::query()->whereIn('name', $permissions)->pluck('id');
        $role->permissions()->syncWithoutDetaching($ids);
    }
}
