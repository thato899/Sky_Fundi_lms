<?php

declare(strict_types=1);

namespace Core\Health\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

final class ProcessQueueCommand extends Command
{
    protected $signature = 'platform:process-queue';

    protected $description = 'Process queued jobs once under a shared cache lock for cron-based hosting.';

    public function handle(): int
    {
        $lock = Cache::lock('platform:process-queue', 70);

        if (! $lock->get()) {
            $this->components->warn('Another bounded queue worker is already active.');

            return self::SUCCESS;
        }

        try {
            $result = Process::timeout(60)->run([
                PHP_BINARY,
                base_path('artisan'),
                'queue:work',
                'database',
                '--stop-when-empty',
                '--tries=3',
                '--timeout=50',
                '--memory=192',
                '--max-time=50',
                '--no-interaction',
            ]);

            if (! $result->successful()) {
                $this->components->error('The bounded queue worker failed. Review the cPanel queue log.');

                return self::FAILURE;
            }

            $this->components->info('Bounded queue processing completed.');

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
