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
        $available = [];

        foreach (array_keys(config('filesystems.disks', [])) as $name) {
            try {
                if ($this->factory->make($name)->isAvailable()) {
                    $available[] = $name;
                }
            } catch (\Throwable) {
                // An unconfigured optional disk is unavailable; it must not
                // prevent local storage or the application health endpoint
                // from being evaluated.
            }
        }

        return $available;
    }
}
