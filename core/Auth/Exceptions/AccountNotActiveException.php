<?php

declare(strict_types=1);

namespace Core\Auth\Exceptions;

use Exception;

/**
 * Thrown when a login attempt is otherwise valid (correct password)
 * but the account's status prevents access — locked, suspended, or
 * deactivated. Mapped to a 403 JSON response by ApiExceptionHandler.
 * See docs/security/policies.md.
 */
final class AccountNotActiveException extends Exception
{
    public static function locked(): self
    {
        return new self('This account is locked due to repeated failed login attempts. Contact a platform administrator.');
    }

    public static function suspended(): self
    {
        return new self('This account has been suspended.');
    }

    public static function deactivated(): self
    {
        return new self('This account has been deactivated.');
    }
}
