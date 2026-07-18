<?php

declare(strict_types=1);

namespace Core\Scheduler\Providers;

use Core\Scheduler\Console\CleanAiCacheCommand;
use Core\Scheduler\Console\CleanQueueCommand;
use Core\Scheduler\Console\CleanTemporaryFilesCommand;
use Core\Scheduler\Console\RunHealthChecksCommand;
use Core\Scheduler\Console\ValidateLicensesCommand;
use Core\Scheduler\Console\ValidateSubscriptionsCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the commands under core/Scheduler/Console and defines
 * their schedule. The schedule itself is also callable directly from
 * routes/console.php per Laravel convention — defined here instead so
 * it lives next to the commands it schedules and every Core service
 * keeps its own concerns in its own folder, consistent with the rest
 * of the platform. See core/Scheduler/README.md.
 */
final class SchedulerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CleanAiCacheCommand::class,
                CleanQueueCommand::class,
                CleanTemporaryFilesCommand::class,
                RunHealthChecksCommand::class,
                ValidateLicensesCommand::class,
                ValidateSubscriptionsCommand::class,
            ]);
        }

        $this->app->booted(function (): void {
            $schedule = $this->app->make(Schedule::class);

            $schedule->command('platform:health-check')->hourly()->withoutOverlapping();
            $schedule->command('platform:validate-licenses')->daily()->withoutOverlapping();
            $schedule->command('platform:validate-subscriptions')->daily()->withoutOverlapping();
            $schedule->command('platform:clean-temp')->daily()->withoutOverlapping();
            $schedule->command('platform:clean-queue')->weekly()->withoutOverlapping();
            $schedule->command('platform:clean-ai-cache')->daily()->withoutOverlapping();
            $schedule->command('platform:backup')->weekly()->withoutOverlapping();
        });
    }
}
