<?php

declare(strict_types=1);

namespace Core\Scheduler\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * `php artisan platform:clean-temp` — deletes files under
 * storage/app/temp older than the given age. Nothing in the platform
 * writes there yet (no modules exist), but future report generation,
 * imports/exports, and AI attachment handling are expected to use it
 * as scratch space — see core/Scheduler/README.md.
 */
final class CleanTemporaryFilesCommand extends Command
{
    protected $signature = 'platform:clean-temp {--hours=24 : Delete files older than this many hours}';

    protected $description = 'Delete stale temporary files from storage/app/temp.';

    public function handle(): int
    {
        $directory = storage_path('app/temp');

        if (! File::isDirectory($directory)) {
            $this->info('No temp directory present — nothing to clean.');

            return self::SUCCESS;
        }

        $cutoff = now()->subHours((int) $this->option('hours'))->getTimestamp();
        $deleted = 0;

        foreach (File::allFiles($directory) as $file) {
            if ($file->getMTime() < $cutoff) {
                File::delete($file->getPathname());
                $deleted++;
            }
        }

        $this->info("{$deleted} temporary file(s) deleted.");

        return self::SUCCESS;
    }
}
