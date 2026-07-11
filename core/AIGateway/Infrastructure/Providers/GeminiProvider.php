<?php

declare(strict_types=1);

namespace Core\AIGateway\Infrastructure\Providers;

/**
 * Placeholder — see AbstractPlaceholderProvider and docs/ai/ai-gateway.md.
 * Implementing this fully means calling Google's Generative Language
 * API generateContent endpoint.
 */
final class GeminiProvider extends AbstractPlaceholderProvider
{
    public function name(): string
    {
        return 'gemini';
    }
}
