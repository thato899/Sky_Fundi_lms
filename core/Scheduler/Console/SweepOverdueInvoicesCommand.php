<?php

declare(strict_types=1);

namespace Core\Scheduler\Console;

use Core\Billing\Application\InvoiceService;
use Illuminate\Console\Command;

/**
 * `php artisan platform:sweep-overdue-invoices` — marks any Issued
 * invoice past its due date Overdue and starts the affected
 * subscription's grace period. Scheduled daily — see
 * Providers\SchedulerServiceProvider.
 */
final class SweepOverdueInvoicesCommand extends Command
{
    protected $signature = 'platform:sweep-overdue-invoices';

    protected $description = 'Mark overdue invoices and start dunning grace periods.';

    public function handle(InvoiceService $invoices): int
    {
        $count = $invoices->markOverdueAndGraceSubscriptions();
        $this->info("{$count} invoice(s) marked overdue.");

        return self::SUCCESS;
    }
}
