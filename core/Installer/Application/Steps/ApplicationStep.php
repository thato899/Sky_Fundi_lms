<?php

declare(strict_types=1);

namespace Core\Installer\Application\Steps;

use Core\Installer\Contracts\InstallerStepInterface;
use Core\Settings\Application\SettingsService;

final class ApplicationStep implements InstallerStepInterface
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function key(): string
    {
        return 'application';
    }

    public function label(): string
    {
        return 'Application name & environment';
    }

    public function isComplete(): bool
    {
        return $this->settings->get('system_name') !== null;
    }

    public function run(array $input): array
    {
        $this->settings->setMany([
            'system_name' => $input['name'],
        ], group: 'general');

        return ['name' => $input['name']];
    }
}
