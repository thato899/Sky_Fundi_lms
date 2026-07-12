<?php

declare(strict_types=1);

namespace Core\Storage\Application;

use Core\Storage\Contracts\FileStorageInterface;
use Core\Storage\Exceptions\StorageDriverNotAvailableException;
use Core\Storage\Infrastructure\Providers\AzureBlobFileStorage;
use Core\Storage\Infrastructure\Providers\GoogleCloudFileStorage;
use Core\Storage\Infrastructure\Providers\LocalFileStorage;
use Core\Storage\Infrastructure\Providers\S3FileStorage;

/**
 * Instantiates a FileStorageInterface implementation for a named disk
 * from config/filesystems.php, keyed off that disk's own `driver`
 * value — deliberately reusing filesystems.php as the single source
 * of disk configuration rather than introducing a second, parallel
 * config file, per the project's "no duplicated logic" rule. Mirrors
 * Core\AIGateway\Application\ProviderFactory. See
 * core/Storage/README.md.
 */
final class StorageProviderFactory
{
    public function make(string $diskName): FileStorageInterface
    {
        $driver = config("filesystems.disks.{$diskName}.driver");

        if ($driver === null) {
            throw StorageDriverNotAvailableException::forDriver($diskName);
        }

        return match ($driver) {
            'local' => new LocalFileStorage($diskName),
            's3' => new S3FileStorage($diskName),
            'azure' => new AzureBlobFileStorage($diskName),
            'gcs' => new GoogleCloudFileStorage($diskName),
            default => throw StorageDriverNotAvailableException::forDriver($diskName),
        };
    }
}
