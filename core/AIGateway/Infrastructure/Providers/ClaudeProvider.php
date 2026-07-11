<?php

declare(strict_types=1);

namespace Core\AIGateway\Infrastructure\Providers;

/**
 * Placeholder — see AbstractPlaceholderProvider and docs/ai/ai-gateway.md.
 * Implementing this fully means calling Anthropic's /v1/messages API.
 */
final class ClaudeProvider extends AbstractPlaceholderProvider
{
    public function name(): string
    {
        return 'claude';
    }
}
