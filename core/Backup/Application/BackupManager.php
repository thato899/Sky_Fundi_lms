<?php

declare(strict_types=1);

namespace Core\Backup\Application;

use Core\Backup\Application\DTOs\BackupResult;
use Core\Backup\Events\BackupCompleted;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Runs every configured backup target (config('backup.targets'), see
 * config/backup.php) into a fresh timestamped directory under
 * config('backup.destination'). One target failing never stops the
 * others — each is wrapped individually. See core/Backup/README.md.
 * Restore is explicitly out of scope — see that same README.
 */
final class BackupManager
{
    public function __construct(
        private readonly \Illuminate\Contracts\Container\Container $container,
    ) {}

    /**
     * @return BackupResult[]
     */
    public function runAll(): array
    {
        $destinationDirectory = rtrim((string) config('backup.destination'), '/').'/'.now()->format('Y-m-d_His');
        File::ensureDirectoryExists($destinationDirectory);

        $results = [];

        foreach (config('backup.targets', []) as $targetClass) {
            $target = $this->container->make($targetClass);

            try {
                $path = $target->backup($destinationDirectory);

                $results[] = new BackupResult(
                    target: $target->name(),
                    success: true,
                    path: $path,
                    sizeBytes: File::exists($path) ? File::size($path) : null,
                );
            } catch (Throwable $e) {
                $results[] = new BackupResult(target: $target->name(), success: false, error: $e->getMessage());
            }
        }

        event(new BackupCompleted($results));

        return $results;
    }
}
