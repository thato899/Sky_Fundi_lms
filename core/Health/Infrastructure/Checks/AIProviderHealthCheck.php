<?php

declare(strict_types=1);

namespace Core\Health\Infrastructure\Checks;

use Core\AIGateway\Application\ProviderRegistry;
use Core\Health\Application\DTOs\HealthCheckResult;
use Core\Health\Contracts\HealthCheckInterface;

final class AIProviderHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly ProviderRegistry $registry,
    ) {}

    public function name(): string
    {
        return 'ai_provider';
    }

    public function check(): HealthCheckResult
    {
        $defaultProvider = config('ai.default_provider');
        $available = $this->registry->availableProviderNames();

        if (! in_array($defaultProvider, $available, true)) {
            return HealthCheckResult::degraded($this->name(), "Default AI provider \"{$defaultProvider}\" is not available", ['default' => $defaultProvider, 'available' => $available]);
        }

        return HealthCheckResult::healthy($this->name(), "Default AI provider \"{$defaultProvider}\" available", ['default' => $defaultProvider, 'available' => $available]);
    }
}
