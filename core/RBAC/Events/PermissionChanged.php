<?php

declare(strict_types=1);

namespace Core\RBAC\Events;

use Core\RBAC\Infrastructure\Models\Role;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever a role's permission set is synced/changed. Distinct
 * from RoleAssigned/RoleRevoked, which are about user<->role
 * membership rather than a role's own permission set.
 */
final class PermissionChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Role $role,
        public readonly array $addedPermissions,
        public readonly array $removedPermissions,
    ) {}
}
