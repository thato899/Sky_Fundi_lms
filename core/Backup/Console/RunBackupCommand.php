<?php

declare(strict_types=1);

namespace Core\Backup\Console;

use Core\Backup\Application\BackupManager;
use Illuminate\Console\Command;

/**
 * `php artisan platform:backup` — see core/Backup/README.md and
 * docs/deployment/README.md for how this is expected to be scheduled.
 */
final class RunBackupCommand extends Command
{
    protected $signature = 'platform:backup';

    protected $description = 'Run all configured platform backup targets (database, storage, configuration, logs).';

    public function handle(BackupManager $manager): int
    {
        $results = $manager->runAll();
        $failed = false;

        foreach ($results as $result) {
            if ($result->success) {
                $this->info("✔ {$result->target}: {$result->path} (".number_format((int) $result->sizeBytes).' bytes)');
            } else {
                $failed = true;
                $this->error("✘ {$result->target}: {$result->error}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
