<?php

declare(strict_types=1);

namespace Core\AIGateway\Application\DTOs;

final readonly class AIResponse
{
    public function __construct(
        public string $content,
        public string $provider,
        public string $model,
        public array $usage = [],
        public array $raw = [],
    ) {}
}
