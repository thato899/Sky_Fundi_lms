<?php

declare(strict_types=1);

namespace Core\RBAC\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level permission gate: `->middleware('permission:core.users.manage')`.
 * Delegates to the standard Gate (which PermissionService is registered
 * against via Gate::before in RBACServiceProvider) so this middleware
 * and $user->can(...) checks never disagree. See docs/security/rbac.md.
 */
final class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException('Authentication is required to access this resource.');
        }

        if (! $user->can($permission)) {
            throw new AuthorizationException("Missing required permission: {$permission}.");
        }

        return $next($request);
    }
}
