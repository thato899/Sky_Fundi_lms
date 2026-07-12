<?php

declare(strict_types=1);

namespace Core\Scheduler\Console;

use Core\Subscriptions\Application\SubscriptionService;
use Illuminate\Console\Command;

/**
 * `php artisan platform:validate-subscriptions` — suspends any
 * subscription whose grace period has ended without renewal.
 * Scheduled daily — see Providers\SchedulerServiceProvider.
 */
final class ValidateSubscriptionsCommand extends Command
{
    protected $signature = 'platform:validate-subscriptions';

    protected $description = 'Suspend subscriptions whose grace period has ended.';

    public function handle(SubscriptionService $subscriptions): int
    {
        $count = $subscriptions->suspendOverdueGracePeriods();
        $this->info("{$count} subscription(s) suspended.");

        return self::SUCCESS;
    }
}
