<?php

declare(strict_types=1);

namespace Modules\Learners\Database\Seeders;

use Core\RBAC\Application\RoleService;
use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Illuminate\Database\Seeder;

final class LearnersPermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
        'learners.view' => 'View the organization learner directory and learner profiles',
        'learners.create' => 'Create profile-only learners',
        'learners.update' => 'Update learner profile information',
        'learners.manage_academic_profile' => 'Update current learner academic placement',
        'learners.manage_status' => 'Transition learner status',
        'learners.archive' => 'Archive learners',
        'learners.restore' => 'Restore archived learners',
        'learners.view_status_history' => 'View immutable learner status history',
        'learners.override_number' => 'Supply a manual learner number during creation',
        'guardians.view' => 'View guardian profiles and learner relationships',
        'guardians.create' => 'Create guardian profiles',
        'guardians.update' => 'Update guardian profiles',
        'guardians.archive' => 'Archive guardian profiles',
        'guardians.manage_relationships' => 'Manage learner guardian relationships and consent',
    ];

    private const ADMIN_PERMISSIONS = [
        'learners.view', 'learners.create', 'learners.update',
        'learners.manage_academic_profile', 'learners.manage_status',
        'learners.archive', 'learners.restore', 'learners.view_status_history',
        'guardians.view', 'guardians.create', 'guardians.update', 'guardians.archive',
        'guardians.manage_relationships',
    ];

    public function run(RoleService $roles): void
    {
        foreach (self::PERMISSIONS as $name => $description) {
            $roles->registerPermission($name, 'learners', $description);
        }

        $all = array_keys(self::PERMISSIONS);
        $this->grant('Super Admin', $all, true);
        $this->grant('Organization Administrator', $all);
        $this->grant('Academic Administrator', self::ADMIN_PERMISSIONS);

        foreach (['Teacher', 'Tutor', 'Learner'] as $roleName) {
            Role::query()->firstOrCreate(['name' => $roleName], ['is_system' => false]);
        }
    }

    private function grant(string $roleName, array $permissions, bool $system = false): void
    {
        $role = Role::query()->firstOrCreate(['name' => $roleName], ['is_system' => $system]);
        $ids = Permission::query()->whereIn('name', $permissions)->pluck('id');
        $role->permissions()->syncWithoutDetaching($ids);
    }
}
