<?php

declare(strict_types=1);

namespace Core\Scheduler\Console;

use Core\Health\Application\HealthCheckManager;
use Core\Logging\Application\PlatformLogger;
use Illuminate\Console\Command;

/**
 * `php artisan platform:health-check` — runs every health check and
 * logs the result to the "system" channel, so a degraded/unhealthy
 * dependency shows up in logs even between admin dashboard visits.
 * Scheduled hourly — see Providers\SchedulerServiceProvider.
 */
final class RunHealthChecksCommand extends Command
{
    protected $signature = 'platform:health-check';

    protected $description = 'Run all health checks and log the result.';

    public function handle(HealthCheckManager $manager, PlatformLogger $logger): int
    {
        $results = $manager->runAll();
        $overall = $manager->overallStatus($results);

        foreach ($results as $result) {
            $this->line("{$result->name}: {$result->status->value} — {$result->message}");
        }

        $logger->system(
            $overall->value === 'unhealthy' ? 'error' : ($overall->value === 'degraded' ? 'warning' : 'info'),
            'health.scheduled_check',
            ['status' => $overall->value],
        );

        return $overall->value === 'unhealthy' ? self::FAILURE : self::SUCCESS;
    }
}
