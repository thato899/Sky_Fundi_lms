<?php

declare(strict_types=1);

namespace Core\Storage\Infrastructure\Providers;

/**
 * Placeholder — see AbstractPlaceholderFileStorage and
 * core/Storage/README.md. Implementing this fully means adding the
 * "league/flysystem-azure-blob-storage" package, registering a
 * custom Laravel filesystem driver for it (Storage::extend), and an
 * "azure" disk in config/filesystems.php.
 */
final class AzureBlobFileStorage extends AbstractPlaceholderFileStorage
{
    public function driverName(): string
    {
        return "azure:{$this->diskName}";
    }
}
