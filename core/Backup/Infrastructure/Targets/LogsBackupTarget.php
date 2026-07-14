<?php

declare(strict_types=1);

namespace Core\Backup\Infrastructure\Targets;

use Core\Backup\Contracts\BackupTargetInterface;
use Core\Backup\Exceptions\BackupFailedException;
use Illuminate\Support\Facades\File;
use ZipArchive;

final class LogsBackupTarget implements BackupTargetInterface
{
    public function name(): string
    {
        return 'logs';
    }

    public function backup(string $destinationDirectory): string
    {
        $source = storage_path('logs');
        $destination = $destinationDirectory.'/logs-'.now()->format('Ymd-His').'.zip';

        $zip = new ZipArchive;

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
