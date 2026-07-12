<?php

declare(strict_types=1);

namespace Core\Health\Contracts;

use Core\Health\Application\DTOs\HealthCheckResult;

/**
 * A single, self-contained check of one platform dependency. Every
 * check must be fast (no long-running network calls beyond a simple
 * ping/ready check) and must never throw — a failing dependency is
 * reported as an Unhealthy HealthCheckResult, not an exception, so one
 * failing check can never take down the health endpoint itself. See
 * core/Health/README.md.
 */
interface HealthCheckInterface
{
    public function name(): string;

    public function check(): HealthCheckResult;
}
