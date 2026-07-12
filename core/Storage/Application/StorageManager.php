<?php

declare(strict_types=1);

namespace Core\Storage\Application;

use Core\Storage\Contracts\FileStorageInterface;

/**
 * The entry point every Core service and module depends on for file
 * persistence — mirrors Core\AIGateway\Application\AIManager's role.
 * Resolution itself is delegated to StorageProviderFactory; this
 * class adds per-request memoization so repeated `disk()` calls for
 * the same disk name don't re-resolve. See core/Storage/README.md.
 */
final class StorageManager
{
    /** @var array<string, FileStorageInterface> */
    private array $resolved = [];

    public function __construct(
        private readonly StorageProviderFactory $factory,
    ) {}

    public function disk(?string $name = null): FileStorageInterface
    {
        $name ??= config('filesystems.default', 'local');

        return $this->resolved[$name] ??= $this->factory->make($name);
    }
}
