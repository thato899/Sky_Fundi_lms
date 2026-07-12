<?php

declare(strict_types=1);

namespace Core\Health\Infrastructure\Checks;

use Core\Health\Application\DTOs\HealthCheckResult;
use Core\Health\Contracts\HealthCheckInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

final class DatabaseHealthCheck implements HealthCheckInterface
{
    public function name(): string
    {
        return 'database';
    }

    public function check(): HealthCheckResult
    {
        $start = microtime(true);

        try {
            DB::select('select 1');

            $elapsedMs = (int) ((microtime(true) - $start) * 1000);

            return $elapsedMs > 500
                ? HealthCheckResult::degraded($this->name(), "Query succeeded but took {$elapsedMs}ms", ['latency_ms' => $elapsedMs])
                : HealthCheckResult::healthy($this->name(), 'Connected', ['latency_ms' => $elapsedMs]);
        } catch (Throwable $e) {
            return HealthCheckResult::unhealthy($this->name(), 'Database connection failed: '.$e->getMessage());
        }
    }
}
