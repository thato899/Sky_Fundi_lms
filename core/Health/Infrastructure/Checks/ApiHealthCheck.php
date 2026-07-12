<?php

declare(strict_types=1);

namespace Core\Health\Infrastructure\Checks;

use Core\Health\Application\DTOs\HealthCheckResult;
use Core\Health\Contracts\HealthCheckInterface;

/**
 * A trivial "the application booted and can run a check" signal —
 * mostly useful as a canary that always reports Healthy unless PHP
 * itself is broken, so a monitoring dashboard has a baseline
 * always-green entry to distinguish "the health endpoint itself is
 * down" from "every dependency reported unhealthy."
 */
final class ApiHealthCheck implements HealthCheckInterface
{
    public function name(): string
    {
        return 'api';
    }

    public function check(): HealthCheckResult
    {
        return HealthCheckResult::healthy($this->name(), 'API is responding', [
            'app_env' => config('app.env'),
            'php_version' => PHP_VERSION,
        ]);
    }
}
