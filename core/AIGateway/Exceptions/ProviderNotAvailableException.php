<?php

declare(strict_types=1);

namespace Core\AIGateway\Exceptions;

use Exception;

/**
 * Thrown when a provider is not configured/enabled, or (for the
 * placeholder future providers — OpenAI, Claude, Gemini) not yet
 * implemented. Mapped to a 503 JSON response so callers can distinguish
 * "AI temporarily unavailable" from a genuine application error — see
 * docs/api/error-handling.md and docs/ai/ai-gateway.md#failure-handling.
 */
final class ProviderNotAvailableException extends Exception
{
    public static function forProvider(string $provider): self
    {
        return new self("The \"{$provider}\" AI provider is not available. Check that it is enabled and configured in config/ai.php.");
    }

    public static function notImplemented(string $provider): self
    {
        return new self("The \"{$provider}\" AI provider adapter is a placeholder and has not been implemented yet. See docs/ai/ai-gateway.md.");
    }
}
