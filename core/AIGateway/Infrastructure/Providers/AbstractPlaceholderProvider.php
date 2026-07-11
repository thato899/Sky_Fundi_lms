<?php

declare(strict_types=1);

namespace Core\AIGateway\Infrastructure\Providers;

use Core\AIGateway\Application\DTOs\AIRequest;
use Core\AIGateway\Application\DTOs\AIResponse;
use Core\AIGateway\Contracts\AIProviderInterface;
use Core\AIGateway\Exceptions\ProviderNotAvailableException;
use Generator;

/**
 * Base for providers documented in docs/ai/ai-gateway.md as "future"
 * but not yet wired up (OpenAI, Claude, Gemini). Each is a real,
 * complete implementation of AIProviderInterface — registered in
 * config/ai.php and fully plug-and-play with AIManager — that reports
 * itself unavailable and fails loudly and clearly if ever selected,
 * rather than being silently absent from the provider registry. This
 * is intentional per core/AIGateway/README.md ("Create placeholders
 * for future providers... The rest should be plug-and-play."):
 * implementing the real HTTP call for each is future work, not a
 * missing contract.
 */
abstract class AbstractPlaceholderProvider implements AIProviderInterface
{
    public function __construct(protected readonly array $config) {}

    public function complete(AIRequest $request): AIResponse
    {
        throw ProviderNotAvailableException::notImplemented($this->name());
    }

    public function stream(AIRequest $request): Generator
    {
        throw ProviderNotAvailableException::notImplemented($this->name());

        yield ''; // unreachable — keeps this a valid Generator return type
    }

    public function isAvailable(): bool
    {
        return false;
    }
}
