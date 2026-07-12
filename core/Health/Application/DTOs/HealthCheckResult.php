<?php

declare(strict_types=1);

namespace Core\Health\Application\DTOs;

use Core\Health\Domain\Enums\HealthStatus;

final readonly class HealthCheckResult
{
    public function __construct(
        public string $name,
        public HealthStatus $status,
        public string $message = '',
        public array $meta = [],
    ) {}

    public static function healthy(string $name, string $message = 'OK', array $meta = []): self
    {
        return new self($name, HealthStatus::Healthy, $message, $meta);
    }

    public static function degraded(string $name, string $message, array $meta = []): self
    {
        return new self($name, HealthStatus::Degraded, $message, $meta);
    }

    public static function unhealthy(string $name, string $message, array $meta = []): self
    {
        return new self($name, HealthStatus::Unhealthy, $message, $meta);
    }
}
