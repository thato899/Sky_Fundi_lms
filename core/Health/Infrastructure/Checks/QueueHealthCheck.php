<?php

declare(strict_types=1);

namespace Core\Health\Infrastructure\Checks;

use Core\Health\Application\DTOs\HealthCheckResult;
use Core\Health\Contracts\HealthCheckInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Reports queue *connectivity* and backlog size, not worker liveness
 * (this process has no way to know if a `queue:work` process is
 * actually running — that's a deployment/process-supervisor concern,
 * documented in docs/deployment/README.md, not something an HTTP
 * request can observe). A large `pending` backlog alongside `zero`
 * recent completions is a signal for an operator to check workers.
 */
final class QueueHealthCheck implements HealthCheckInterface
{
    public function name(): string
    {
        return 'queue';
    }

    public function check(): HealthCheckResult
    {
        if (config('queue.default') !== 'database') {
            // Redis/other driver backlogs aren't cheaply introspectable
            // here without a Redis client call; report configured and
            // move on rather than guessing.
            return HealthCheckResult::healthy($this->name(), 'Configured (driver: '.config('queue.default').')', ['driver' => config('queue.default')]);
        }

        try {
            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();

            if ($failed > 50) {
                return HealthCheckResult::degraded($this->name(), "{$failed} failed jobs recorded", ['pending' => $pending, 'failed' => $failed]);
            }

            return HealthCheckResult::healthy($this->name(), "{$pending} pending job(s)", ['pending' => $pending, 'failed' => $failed]);
        } catch (Throwable $e) {
            return HealthCheckResult::unhealthy($this->name(), 'Could not read queue tables: '.$e->getMessage());
        }
    }
}
