<?php

declare(strict_types=1);

namespace Core\RBAC\Application;

use Core\Users\Infrastructure\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * The single place authorization decisions are resolved. Registered
 * against Laravel's Gate via Gate::before() in RBACServiceProvider, so
 * every $user->can('x') / @can('x') check in the application ultimately
 * flows through here — see docs/security/rbac.md#enforcement-points and
 * the "never hardcode roles, everything must use permissions" rule.
 */
final class PermissionService
{
    private const CACHE_TTL_SECONDS = 300;

    public function check(User $user, string $permission): bool
    {
        return in_array($permission, $this->permissionsFor($user), true);
    }

    /**
     * All permission names granted to a user, whether via a role or a
     * direct override (see docs/security/rbac.md), cached briefly per
     * user so authorization checks don't repeatedly hit the database
     * within a single request cycle or burst of requests.
     */
    public function permissionsFor(User $user): array
    {
        return Cache::remember(
            "rbac:user:{$user->id}:permissions",
            self::CACHE_TTL_SECONDS,
            function () use ($user): array {
                $viaRoles = $user->roles()
                    ->with('permissions')
                    ->get()
                    ->flatMap(fn ($role) => $role->permissions->pluck('name'));

                $direct = $user->directPermissions()->pluck('name');

                return $viaRoles->merge($direct)->unique()->values()->all();
            },
        );
    }

    public function forgetCacheFor(User $user): void
    {
        Cache::forget("rbac:user:{$user->id}:permissions");
    }
}
