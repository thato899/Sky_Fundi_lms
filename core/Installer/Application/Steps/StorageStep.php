<?php

declare(strict_types=1);

namespace Core\Installer\Application\Steps;

use Core\Installer\Contracts\InstallerStepInterface;
use Core\Settings\Application\SettingsService;
use Core\Storage\Application\StorageProviderRegistry;

final class StorageStep implements InstallerStepInterface
{
    public function __construct(
        private readonly StorageProviderRegistry $disks,
        private readonly SettingsService $settings,
    ) {}

    public function key(): string
    {
        return 'storage';
    }

    public function label(): string
    {
        return 'Storage disk';
    }

    public function isComplete(): bool
    {
        return $this->settings->get('default_disk') !== null;
    }

    public function run(array $input): array
    {
        $disk = $input['disk'];

        $this->settings->set('default_disk', $disk, group: 'storage');

        return ['disk' => $disk, 'available_disks' => $this->disks->availableDiskNames()];
    }
}
