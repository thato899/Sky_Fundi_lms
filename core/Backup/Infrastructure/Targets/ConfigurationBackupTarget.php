<?php

declare(strict_types=1);

namespace Core\Backup\Infrastructure\Targets;

use Core\Backup\Contracts\BackupTargetInterface;
use Core\Backup\Exceptions\BackupFailedException;
use Illuminate\Support\Facades\File;
use ZipArchive;

/**
 * Backs up `config/*.php` only — **never** the real `.env` file, which
 * holds live secrets (see docs/security/policies.md#secrets-management).
 * A restored configuration backup still needs its `.env` recreated
 * from the deployment's own secret manager.
 */
final class ConfigurationBackupTarget implements BackupTargetInterface
{
    public function name(): string
    {
        return 'configuration';
    }

    public function backup(string $destinationDirectory): string
    {
        $source = config_path();
        $destination = $destinationDirectory.'/configuration-'.now()->format('Ymd-His').'.zip';

        $zip = new ZipArchive();

        if ($zip->open($destination, ZipArchive::CREATE) !== true) {
            throw BackupFailedException::forTarget($this->name(), "Could not create archive at {$destination}.");
        }

        foreach (File::files($source) as $file) {
            $zip->addFile($file->getPathname(), $file->getFilename());
        }

        $zip->close();

        return $destination;
    }
}
