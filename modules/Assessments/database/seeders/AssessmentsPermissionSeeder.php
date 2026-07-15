<?php

declare(strict_types=1);

namespace Modules\Assessments\Database\Seeders;

use Core\RBAC\Application\RoleService;
use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Illuminate\Database\Seeder;

final class AssessmentsPermissionSeeder extends Seeder
{
    public const PERMISSIONS = ['assessments.view', 'assessments.create', 'assessments.update', 'assessments.mark', 'assessments.finalize', 'assessments.reopen', 'assessments.cancel', 'assessments.release', 'assessments.export', 'assessments.view_reports', 'assessment_categories.manage'];

    public function run(RoleService $roles): void
    {
        foreach (self::PERMISSIONS as $permission) {
            $roles->registerPermission($permission, 'assessments', str_replace(['assessments.', 'assessment_categories.', '_'], ['', '', ' '], ucfirst($permission)));
        }
        foreach (['Super Admin', 'Organization Administrator', 'Academic Administrator'] as $name) {
            $this->grant($name, self::PERMISSIONS, $name === 'Super Admin');
        }
        foreach (['Teacher', 'Tutor'] as $name) {
            $this->grant($name, ['assessments.view', 'assessments.create', 'assessments.update', 'assessments.mark', 'assessments.finalize', 'assessments.export']);
        }
    }

    private function grant(string $name, array $permissions, bool $system = false): void
    {
        $role = Role::query()->firstOrCreate(['name' => $name], ['is_system' => $system]);
        $role->permissions()->syncWithoutDetaching(Permission::query()->whereIn('name', $permissions)->pluck('id'));
    }
}
