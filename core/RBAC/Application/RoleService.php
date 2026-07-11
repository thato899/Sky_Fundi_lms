<?php

declare(strict_types=1);

namespace Core\RBAC\Application;

use Core\AuditLogs\Application\AuditLogService;
use Core\RBAC\Events\PermissionChanged;
use Core\RBAC\Events\RoleAssigned;
use Core\RBAC\Events\RoleRevoked;
use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Support\Str;

/**
 * Orchestrates role/permission management. Controllers call this
 * service rather than manipulating pivot tables directly, per
 * docs/architecture/clean-architecture.md#application--service-layer.
 */
final class RoleService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly PermissionService $permissions,
    ) {}

    public function createRole(string $name, ?string $description = null, array $permissionNames = []): Role
    {
        $role = Role::create([
            'name' => $name,
            'description' => $description,
            'is_system' => false,
        ]);

        if ($permissionNames !== []) {
            $this->syncPermissions($role, $permissionNames);
        }

        $this->auditLog->record(
            action: 'role.created',
            target: $role,
            after: ['name' => $role->name, 'permissions' => $permissionNames],
        );

        return $role;
    }

    public function syncPermissions(Role $role, array $permissionNames): Role
    {
        $before = $role->permissions()->pluck('name')->all();

        $permissionIds = Permission::query()->whereIn('name', $permissionNames)->pluck('id', 'name');
        $role->permissions()->sync($permissionIds->values());

        $after = array_values($permissionNames);
        $added = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));

        if ($added !== [] || $removed !== []) {
            event(new PermissionChanged($role, $added, $removed));

            $this->auditLog->record(
                action: 'role.permissions_synced',
                target: $role,
                before: ['permissions' => $before],
                after: ['permissions' => $after],
            );

            $this->forgetCacheForRoleMembers($role);
        }

        return $role->fresh('permissions');
    }

    public function assignRoleToUser(User $user, Role $role): void
    {
        if ($user->roles()->where('roles.id', $role->id)->doesntExist()) {
            $user->roles()->attach($role->id);

            event(new RoleAssigned($user, $role));

            $this->auditLog->record(
                action: 'role.assigned',
                target: $user,
                after: ['role' => $role->name],
            );

            $this->permissions->forgetCacheFor($user);
        }
    }

    public function revokeRoleFromUser(User $user, Role $role): void
    {
        $user->roles()->detach($role->id);

        event(new RoleRevoked($user, $role));

        $this->auditLog->record(
            action: 'role.revoked',
            target: $user,
            after: ['role' => $role->name],
        );

        $this->permissions->forgetCacheFor($user);
    }

    public function deleteRole(Role $role): void
    {
        if ($role->is_system) {
            throw new \DomainException("System role \"{$role->name}\" cannot be deleted.");
        }

        $this->auditLog->record(action: 'role.deleted', target: $role, before: ['name' => $role->name]);

        $role->delete();
    }

    /**
     * Deliberately generates a stable slug-safe permission name so
     * modules registering permissions from their manifest (see
     * docs/architecture/module-system.md#module-manifest-modulejson)
     * get consistent naming without hand-writing every string.
     */
    public function registerPermission(string $name, string $module, ?string $description = null): Permission
    {
        return Permission::query()->firstOrCreate(
            ['name' => Str::lower($name)],
            ['module' => $module, 'description' => $description],
        );
    }

    private function forgetCacheForRoleMembers(Role $role): void
    {
        $role->loadMissing('users');

        foreach ($role->users as $user) {
            $this->permissions->forgetCacheFor($user);
        }
    }
}
