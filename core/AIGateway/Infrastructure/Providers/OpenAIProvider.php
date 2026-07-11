<?php

declare(strict_types=1);

namespace Core\AIGateway\Infrastructure\Providers;

/**
 * Placeholder — see AbstractPlaceholderProvider and docs/ai/ai-gateway.md.
 * Implementing this fully means calling OpenAI's chat completions API
 * with the same request/response shaping pattern as DeepSeekProvider
 * (they share an OpenAI-compatible wire format).
 */
final class OpenAIProvider extends AbstractPlaceholderProvider
{
    public function name(): string
    {
        return 'openai';
    }
}
