<?php

declare(strict_types=1);

namespace Core\Health\Infrastructure\Checks;

use Core\Health\Application\DTOs\HealthCheckResult;
use Core\Health\Contracts\HealthCheckInterface;
use Core\Storage\Application\StorageProviderRegistry;

final class StorageHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly StorageProviderRegistry $registry,
    ) {}

    public function name(): string
    {
        return 'storage';
    }

    public function check(): HealthCheckResult
    {
        $defaultDisk = config('filesystems.default');
        $available = $this->registry->availableDiskNames();

        if (! in_array($defaultDisk, $available, true)) {
            return HealthCheckResult::unhealthy($this->name(), "Default disk \"{$defaultDisk}\" is not available", ['default' => $defaultDisk, 'available' => $available]);
        }

        return HealthCheckResult::healthy($this->name(), "Default disk \"{$defaultDisk}\" available", ['default' => $defaultDisk, 'available' => $available]);
    }
}
