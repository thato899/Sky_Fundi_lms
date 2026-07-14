<?php

declare(strict_types=1);

namespace Core\Identity\Application;

use Core\Identity\Infrastructure\Models\Membership;

/** Resolves membership role permissions, rejecting permissions for disabled modules. */
final class PermissionResolver
{
    public function permissions(Membership $membership): array
    {
        $modules = $membership->organization->modules->where('enabled', true)->pluck('module_name')->all();

        return $membership->role?->permissions->filter(fn ($permission) => $permission->module === 'core' || in_array($permission->module, $modules, true))->pluck('name')->values()->all() ?? [];
    }

    public function allows(Membership $membership, string $permission): bool
    {
        return in_array($permission, $this->permissions($membership), true);
    }
}
