<?php

declare(strict_types=1);

namespace Core\Storage\Application;

use Core\Storage\Contracts\FileStorageInterface;
use Core\Storage\Exceptions\StorageDriverNotAvailableException;
use Core\Storage\Infrastructure\Providers\LocalFileStorage;

/**
 * Resolves the configured FileStorageInterface implementation. Core
 * services and modules should type-hint FileStorageInterface (bound to
 * this manager's default() result in StorageServiceProvider) rather
 * than calling this manager directly, except where a caller genuinely
 * needs a non-default disk. See core/Storage/README.md.
 */
final class StorageManager
{
    /** @var array<string, FileStorageInterface> */
    private array $resolved = [];

    public function disk(?string $name = null): FileStorageInterface
    {
        $name ??= config('filesystems.default', 'local');

        return $this->resolved[$name] ??= $this->makeDriver($name);
    }

    /**
     * @throws StorageDriverNotAvailableException
     */
    private function makeDriver(string $name): FileStorageInterface
    {
        return match ($name) {
            'local', 'public' => new LocalFileStorage($name),
            // 's3', 'azure', 'gcs' will be added here as those drivers
            // are implemented — see core/Storage/README.md ("Future
            // usage") and config/filesystems.php.
            default => throw StorageDriverNotAvailableException::forDriver($name),
        };
    }
}
