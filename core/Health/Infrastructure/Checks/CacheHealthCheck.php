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
        $probeValue = Str::random(8);

        try {
            Cache::put($probeKey, $probeValue, 10);
            $roundTripped = Cache::get($probeKey) === $probeValue;
            Cache::forget($probeKey);

            return $roundTripped
                ? HealthCheckResult::healthy($this->name(), 'Read/write round-trip succeeded', ['driver' => config('cache.default')])
                : HealthCheckResult::degraded($this->name(), 'Cache write succeeded but read-back did not match', ['driver' => config('cache.default')]);
        } catch (Throwable $e) {
            return HealthCheckResult::unhealthy($this->name(), 'Cache is unreachable: '.$e->getMessage());
        }
    }
}
