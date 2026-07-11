<?php

declare(strict_types=1);

namespace Database\Seeders;

use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds the platform's system roles — see core/RBAC/README.md
 * ("Create seeders for Super Admin, Platform Administrator, Support,
 * Developer. No educational roles yet."). System roles (is_system =
 * true) cannot be deleted through the API — see
 * Core\RBAC\Application\RoleService::deleteRole(). Idempotent.
 */
final class RoleSeeder extends Seeder
{
    /**
     * Role name => permission names, or the literal '*' for "every
     * currently registered permission" (Super Admin only — never
     * hardcode this shortcut for any other role, per the project's
     * "never hardcode roles, everything must use permissions" rule;
     * even Super Admin's '*' resolves to real, explicit permission
     * rows at seed time, not a special-cased bypass in code).
     */
    private const ROLE_PERMISSIONS = [
        'Super Admin' => '*',
        'Platform Administrator' => [
            'core.users.manage',
            'core.users.view',
            'core.branding.manage',
            'core.settings.manage',
            'core.billing.manage',
            'core.ai.manage',
            'core.logs.view',
            'core.modules.manage',
        ],
        'Support' => [
            'core.users.view',
            'core.logs.view',
        ],
        'Developer' => [
            'core.modules.manage',
            'core.ai.manage',
            'core.settings.manage',
            'core.logs.view',
        ],
    ];

    private const ROLE_DESCRIPTIONS = [
        'Super Admin' => 'Unrestricted platform access, including role and permission management. Reserved for platform owners.',
        'Platform Administrator' => 'Day-to-day platform administration: users, branding, settings, billing, AI, modules. Cannot alter roles/permissions.',
        'Support' => 'Read-only access for support staff assisting tenants: user lookups and log review.',
        'Developer' => 'Technical operations: module management, AI provider configuration, settings, and log access.',
    ];

    public function run(): void
    {
        $allPermissionNames = Permission::query()->pluck('name')->all();

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::query()->updateOrCreate(
                ['name' => $roleName],
                ['description' => self::ROLE_DESCRIPTIONS[$roleName], 'is_system' => true],
            );

            $permissionNames = $permissions === '*' ? $allPermissionNames : $permissions;
            $permissionIds = Permission::query()->whereIn('name', $permissionNames)->pluck('id');

            $role->permissions()->sync($permissionIds);
        }
    }
}
