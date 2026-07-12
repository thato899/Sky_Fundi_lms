<?php

declare(strict_types=1);

namespace Core\Backup\Infrastructure\Targets;

use Core\Backup\Contracts\BackupTargetInterface;
use Core\Backup\Exceptions\BackupFailedException;
use Illuminate\Support\Facades\File;
use ZipArchive;

final class StorageBackupTarget implements BackupTargetInterface
{
    public function name(): string
    {
        return 'storage';
    }

    public function backup(string $destinationDirectory): string
    {
        $source = storage_path('app');
        $destination = $destinationDirectory.'/storage-'.now()->format('Ymd-His').'.zip';

        $zip = new ZipArchive();

        if ($zip->open($destination, ZipArchive::CREATE) !== true) {
            throw BackupFailedException::forTarget($this->name(), "Could not create archive at {$destination}.");
        }

        foreach (File::allFiles($source) as $file) {
            $zip->addFile($file->getPathname(), $file->getRelativePathname());
        }

        $zip->close();

        return $destination;
    }
}
