<?php

declare(strict_types=1);

namespace Core\Health\Infrastructure\Checks;

use Core\Health\Application\DTOs\HealthCheckResult;
use Core\Health\Contracts\HealthCheckInterface;
use Core\Mail\Application\MailProviderRegistry;

final class MailHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly MailProviderRegistry $registry,
    ) {}

    public function name(): string
    {
        return 'mail';
    }

    public function check(): HealthCheckResult
    {
        $defaultProvider = config('mail_providers.default_provider');
        $available = $this->registry->availableProviderNames();

        if (! in_array($defaultProvider, $available, true)) {
            return HealthCheckResult::degraded($this->name(), "Default provider \"{$defaultProvider}\" is not configured", ['default' => $defaultProvider, 'available' => $available]);
        }

        return HealthCheckResult::healthy($this->name(), "Default provider \"{$defaultProvider}\" available", ['default' => $defaultProvider, 'available' => $available]);
    }
}
