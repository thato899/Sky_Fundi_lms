<?php

declare(strict_types=1);

namespace Core\Auth\Listeners;

use Core\Auth\Application\AuthService;
use Core\Users\Events\UserLocked;
use Core\Users\Events\UserSuspended;

/**
 * Ensures a token issued before a status change stops working
 * immediately rather than remaining valid until it naturally expires.
 * See docs/security/policies.md#session-policy.
 */
final class RevokeTokensOnAccountLock
{
    public function __construct(
        private readonly AuthService $auth,
    ) {}

    public function handleSuspended(UserSuspended $event): void
    {
        $this->auth->revokeAllTokens($event->user);
    }

    public function handleLocked(UserLocked $event): void
    {
        $this->auth->revokeAllTokens($event->user);
    }
}
