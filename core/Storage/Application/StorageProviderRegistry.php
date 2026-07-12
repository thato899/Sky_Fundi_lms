<?php

declare(strict_types=1);

namespace Core\Storage\Application;

use Core\Storage\Contracts\FileStorageInterface;

/**
 * Answers "what storage disks exist and which are actually usable
 * right now" — used by the platform Health Centre (see
 * core/Health/README.md) and admin storage settings. Mirrors
 * Core\AIGateway\Application\ProviderRegistry. See
 * core/Storage/README.md.
 */
final class StorageProviderRegistry
{
    public function __construct(
        private readonly StorageProviderFactory $factory,
    ) {}

    /**
     * @return array<string, FileStorageInterface>
     */
    public function all(): array
    {
        $disks = [];

        foreach (array_keys(config('filesystems.disks', [])) as $name) {
            $disks[$name] = $this->factory->make($name);
        }

        return $disks;
    }

    /**
     * @return string[]
     */
    public function availableDiskNames(): array
    {
        return array_keys(array_filter(
            $this->all(),
            fn (FileStorageInterface $disk) => $disk->isAvailable(),
        ));
    }
}
