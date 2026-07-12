<?php

declare(strict_types=1);

namespace Core\Storage\Infrastructure\Providers;

/**
 * Placeholder — see AbstractPlaceholderFileStorage and
 * core/Storage/README.md. Implementing this fully means adding the
 * "league/flysystem-google-cloud-storage" package and a "gcs" disk in
 * config/filesystems.php.
 */
final class GoogleCloudFileStorage extends AbstractPlaceholderFileStorage
{
    public function driverName(): string
    {
        return "gcs:{$this->diskName}";
    }
}
