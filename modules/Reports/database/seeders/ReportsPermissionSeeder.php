<?php

declare(strict_types=1);

namespace Modules\Reports\Database\Seeders;

use Core\RBAC\Application\RoleService;
use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Illuminate\Database\Seeder;

final class ReportsPermissionSeeder extends Seeder
{
    public const PERMISSIONS = ['reports.view', 'reports.generate', 'reports.update', 'reports.review', 'reports.approve', 'reports.publish', 'reports.withdraw', 'reports.export_pdf', 'reports.export_csv', 'reports.manage_grading_scales', 'reports.manage_periods', 'reports.manage_templates', 'reports.manage_comments'];

    public function run(RoleService $roles): void
    {
        foreach (self::PERMISSIONS as $p) {
            $roles->registerPermission($p, 'reports', str_replace(['reports.', '_'], ['', ' '], ucfirst($p)));
        } foreach (['Super Admin', 'Organization Administrator', 'Academic Administrator'] as $name) {
            $this->grant($name, self::PERMISSIONS, $name === 'Super Admin');
        } foreach (['Teacher', 'Tutor'] as $name) {
            $this->grant($name, ['reports.view', 'reports.manage_comments']);
        }
    }

    private function grant(string $name, array $permissions, bool $system = false): void
    {
        $role = Role::query()->firstOrCreate(['name' => $name], ['is_system' => $system]);
        $role->permissions()->syncWithoutDetaching(Permission::query()->whereIn('name', $permissions)->pluck('id'));
    }
}
