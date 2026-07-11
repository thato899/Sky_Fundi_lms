<?php

declare(strict_types=1);

namespace Core\AIGateway\Application\DTOs;

/**
 * A provider-agnostic request. Modules build one of these and pass it
 * to AIManager::complete()/stream() — they never see or build a
 * provider-specific payload. See docs/ai/ai-gateway.md.
 */
final readonly class AIRequest
{
    public function __construct(
        public string $prompt,
        public string $capability = 'completion',
        public ?string $tenantId = null,
        public ?string $moduleId = null,
        public ?string $preferredProvider = null,
        public float $temperature = 0.7,
        public int $maxTokens = 1024,
        public array $metadata = [],
    ) {}
}
