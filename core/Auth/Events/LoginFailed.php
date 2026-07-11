<?php

declare(strict_types=1);

namespace Core\Auth\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired for every failed login attempt, including attempts against an
 * email that doesn't exist — deliberately does not carry a User
 * instance for that reason. See docs/security/policies.md.
 */
final class LoginFailed
{
    use Dispatchable;

    public function __construct(
        public readonly string $email,
        public readonly string $ipAddress,
    ) {}
}
