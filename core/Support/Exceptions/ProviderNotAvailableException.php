<?php

declare(strict_types=1);

namespace Core\Support\Exceptions;

use Exception;

/**
 * Generic "this provider isn't configured/implemented" exception,
 * shared by every *Manager built on the provider pattern (AI Gateway,
 * Storage, Mail — see Core\Support\Contracts\ProviderInterface).
 * Core\AIGateway keeps its own ProviderNotAvailableException (shipped
 * before this shared one existed, and already depended on by
 * existing tests — not touched here per "do not recreate existing
 * work"). New provider-pattern services added in this layer (Storage
 * drivers, Mail providers) throw this one instead of each inventing
 * their own near-identical exception class.
 */
final class ProviderNotAvailableException extends Exception
{
    public static function forProvider(string $kind, string $provider): self
    {
        return new self("The \"{$provider}\" {$kind} provider is not available. Check that it is enabled and configured.");
    }

    public static function notImplemented(string $kind, string $provider): self
    {
        return new self("The \"{$provider}\" {$kind} provider adapter is a placeholder and has not been implemented yet.");
    }
}
