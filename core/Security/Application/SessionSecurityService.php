<?php

declare(strict_types=1);

namespace Core\Security\Application;

use Core\Users\Infrastructure\Models\User;
use Illuminate\Support\Collection;

/**
 * Session management on top of Sanctum's personal_access_tokens table
 * (see Core\Auth) — deliberately does not duplicate token storage,
 * just adds a user-facing "your active sessions" view and the ability
 * to revoke one or all-but-current. See core/Security/README.md.
 */
final class SessionSecurityService
{
    public function activeSessionsFor(User $user): Collection
    {
        return $user->tokens()->orderByDesc('last_used_at')->get([
            'id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at',
        ]);
    }

    public function revoke(User $user, int|string $tokenId): bool
    {
        return (bool) $user->tokens()->where('id', $tokenId)->delete();
    }

    public function revokeAllExcept(User $user, int|string $currentTokenId): int
    {
        return $user->tokens()->where('id', '!=', $currentTokenId)->delete();
    }
}
