<?php

declare(strict_types=1);

namespace Core\Scheduler\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * `php artisan platform:clean-queue` — prunes old failed_jobs rows
 * beyond a retention window, so the failed-jobs table doesn't grow
 * unbounded. Does not touch pending jobs. See
 * core/Scheduler/README.md.
 */
final class CleanQueueCommand extends Command
{
    protected $signature = 'platform:clean-queue {--days=30 : Delete failed jobs older than this many days}';

    protected $description = 'Prune old failed_jobs records.';

    public function handle(): int
    {
        if (config('queue.default') !== 'database' && ! DB::getSchemaBuilder()->hasTable('failed_jobs')) {
            $this->info('No failed_jobs table present — nothing to clean.');

            return self::SUCCESS;
        }

        $deleted = DB::table('failed_jobs')
            ->where('failed_at', '<', now()->subDays((int) $this->option('days')))
            ->delete();

        $this->info("{$deleted} old failed job record(s) deleted.");

        return self::SUCCESS;
    }
}
