<?php

declare(strict_types=1);

namespace Core\Storage\Infrastructure\Providers;

use Core\Storage\Contracts\FileStorageInterface;
use Core\Storage\Exceptions\StorageDriverNotAvailableException;

/**
 * Base for storage drivers documented as "future" but not yet wired
 * up — mirrors Core\AIGateway\Infrastructure\Providers\
 * AbstractPlaceholderProvider exactly, for the same reason: a real,
 * registered, plug-and-play implementation of FileStorageInterface
 * that fails loudly and clearly if ever selected, rather than being
 * silently absent from the provider registry. See
 * core/Storage/README.md.
 */
abstract class AbstractPlaceholderFileStorage implements FileStorageInterface
{
    public function __construct(protected readonly string $diskName) {}

    public function put(string $path, mixed $contents, array $options = []): string
    {
        throw StorageDriverNotAvailableException::forDriver($this->driverName());
    }

    public function get(string $path): string
    {
        throw StorageDriverNotAvailableException::forDriver($this->driverName());
    }

    public function exists(string $path): bool
    {
        throw StorageDriverNotAvailableException::forDriver($this->driverName());
    }

    public function delete(string $path): bool
    {
        throw StorageDriverNotAvailableException::forDriver($this->driverName());
    }

    public function url(string $path): ?string
    {
        throw StorageDriverNotAvailableException::forDriver($this->driverName());
    }

    public function size(string $path): int
    {
        throw StorageDriverNotAvailableException::forDriver($this->driverName());
    }

    public function isAvailable(): bool
    {
        return false;
    }
}
