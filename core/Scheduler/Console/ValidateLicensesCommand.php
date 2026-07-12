<?php

declare(strict_types=1);

namespace Core\Scheduler\Console;

use Core\Licensing\Application\LicenseService;
use Illuminate\Console\Command;

/**
 * `php artisan platform:validate-licenses` — moves any License whose
 * expiry_date has passed into LicenseStatus::Expired. Scheduled daily
 * — see Providers\SchedulerServiceProvider.
 */
final class ValidateLicensesCommand extends Command
{
    protected $signature = 'platform:validate-licenses';

    protected $description = 'Expire any licenses past their expiry date.';

    public function handle(LicenseService $licenses): int
    {
        $count = $licenses->expireOverdueLicenses();
        $this->info("{$count} license(s) expired.");

        return self::SUCCESS;
    }
}
