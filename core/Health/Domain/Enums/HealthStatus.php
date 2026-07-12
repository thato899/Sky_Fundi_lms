<?php

declare(strict_types=1);

namespace Core\Health\Domain\Enums;

enum HealthStatus: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Unhealthy = 'unhealthy';

    /**
     * Combines this status with another, always keeping the worse of
     * the two — used to roll up individual check results into one
     * overall platform status. Unhealthy beats Degraded beats Healthy.
     */
    public function worseOf(self $other): self
    {
        $severity = [self::Healthy, self::Degraded, self::Unhealthy];

        return array_search($this, $severity, true) >= array_search($other, $severity, true) ? $this : $other;
    }
}
