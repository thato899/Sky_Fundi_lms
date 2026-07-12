<?php

declare(strict_types=1);

namespace Core\Installer\Application\Steps;

use Core\Installer\Contracts\InstallerStepInterface;
use Core\Settings\Application\SettingsService;

final class LocalizationStep implements InstallerStepInterface
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function key(): string
    {
        return 'localization';
    }

    public function label(): string
    {
        return 'Timezone & language';
    }

    public function isComplete(): bool
    {
        return $this->settings->get('default_timezone') !== null;
    }

    public function run(array $input): array
    {
        $this->settings->setMany([
            'default_timezone' => $input['timezone'],
            'default_locale' => $input['locale'],
        ], group: 'general');

        return ['timezone' => $input['timezone'], 'locale' => $input['locale']];
    }
}
