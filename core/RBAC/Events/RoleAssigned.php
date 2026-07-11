<?php

declare(strict_types=1);

namespace Core\RBAC\Events;

use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a role is attached to a user. See
 * docs/architecture/module-system.md#cross-module-communication —
 * modules that care about role assignment subscribe to this rather
 * than being called synchronously.
 */
final class RoleAssigned
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Role $role,
    ) {}
}
