<?php

declare(strict_types=1);

namespace Core\AIGateway\Contracts;

use Core\AIGateway\Application\DTOs\AIRequest;
use Core\AIGateway\Application\DTOs\AIResponse;
use Core\AIGateway\Exceptions\ProviderNotAvailableException;
use Generator;

/**
 * The contract every AI provider adapter implements. No module or Core
 * service may talk to a provider SDK directly — everything resolves
 * through Core\AIGateway\Application\AIManager, which selects and
 * calls a provider through this interface. See docs/ai/ai-gateway.md.
 */
interface AIProviderInterface
{
    /**
     * A single, complete response for the given request.
     *
     * @throws ProviderNotAvailableException
     */
    public function complete(AIRequest $request): AIResponse;

    /**
     * Streams response chunks as they arrive. Implementations that
     * cannot stream may fall back to yielding the full complete()
     * response as a single chunk.
     *
     * @return Generator<int, string>
     *
     * @throws ProviderNotAvailableException
     */
    public function stream(AIRequest $request): Generator;

    /**
     * Whether this provider is currently configured and reachable
     * (has credentials/base URL, is enabled in config/ai.php). Does
     * not guarantee a live network check — see docs/ai/ai-gateway.md.
     */
    public function isAvailable(): bool;

    /**
     * The provider's registry key, e.g. "ollama", "deepseek".
     */
    public function name(): string;
}
