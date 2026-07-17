<?php

declare(strict_types=1);

namespace Core\Health\Infrastructure\Checks;

use Core\Health\Application\DTOs\HealthCheckResult;
use Core\Health\Contracts\HealthCheckInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

final class CacheHealthCheck implements HealthCheckInterface
{
    public function name(): string
    {
        return 'cache';
    }

    public function check(): HealthCheckResult
    {
        $probeKey = 'health-check:'.Str::random(8);

        try {
            Cache::get($probeKey);

            return HealthCheckResult::healthy($this->name(), 'Cache responded.', ['driver' => config('cache.default')]);
        } catch (Throwable) {
            return HealthCheckResult::unhealthy($this->name(), 'Cache is unreachable.');
        }
    }
}
