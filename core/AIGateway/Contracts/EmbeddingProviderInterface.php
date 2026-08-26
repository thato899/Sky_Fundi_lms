<?php

declare(strict_types=1);

namespace Core\AIGateway\Contracts;

use Core\AIGateway\Exceptions\ProviderNotAvailableException;

/**
 * An optional capability a provider adapter may additionally implement
 * alongside AIProviderInterface — see docs/adr/011-materials-retrieval.md.
 * Kept as a separate interface rather than new AIProviderInterface
 * methods so existing providers that have no embeddings endpoint
 * (Ollama's default model, DeepSeek, OpenAI, Claude) need no change
 * and are not forced to implement something they don't support.
 * Core\AIGateway\Application\AIManager::embed() resolves a provider
 * implementing this interface the same way complete()/stream()
 * resolve an AIProviderInterface provider.
 */
interface EmbeddingProviderInterface
{
    /**
     * @return list<float> the embedding vector
     *
     * @throws ProviderNotAvailableException
     */
    public function embed(string $text): array;
}
