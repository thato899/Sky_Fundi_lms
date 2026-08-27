<?php

declare(strict_types=1);

namespace Modules\Leaderboards\Database\Seeders;

use Core\RBAC\Application\RoleService;
use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Illuminate\Database\Seeder;

final class LeaderboardsPermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
        'leaderboards.manage' => 'Generate, publish, and unpublish academic and sports leaderboards, and post the Sportsperson of the Week',
        'leaderboards.view_organization' => 'View the full ranked list of any leaderboard, even before it is published',
        'sports_records.manage' => 'Log and remove sports results used to build the sports leaderboard',
    ];

    public function run(RoleService $roles): void
    {
        foreach (self::PERMISSIONS as $name => $description) {
            $roles->registerPermission($name, 'leaderboards', $description);
        }

        $all = array_keys(self::PERMISSIONS);
        $this->grant('Super Admin', $all, true);
        // Leaderboard arrangement/publishing is a principal-level
        // decision by design (per the feature request) — not extended
        // to Academic Administrator the way most org-wide capabilities
        // in this codebase are.
        $this->grant('Organization Administrator', $all);
        $this->grant('Academic Administrator', ['leaderboards.view_organization', 'sports_records.manage']);
        $this->grant('Teacher', ['leaderboards.view_organization', 'sports_records.manage']);
        $this->grant('Tutor', ['sports_records.manage']);
    }

    private function grant(string $roleName, array $permissions, bool $system = false): void
    {
        $role = Role::query()->firstOrCreate(['name' => $roleName], ['is_system' => $system]);
        $ids = Permission::query()->whereIn('name', $permissions)->pluck('id');
        $role->permissions()->syncWithoutDetaching($ids);
    }
}
