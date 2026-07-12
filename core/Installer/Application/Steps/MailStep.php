<?php

declare(strict_types=1);

namespace Core\Installer\Application\Steps;

use Core\Installer\Contracts\InstallerStepInterface;
use Core\Mail\Application\MailProviderRegistry;
use Core\Settings\Application\SettingsService;

final class MailStep implements InstallerStepInterface
{
    public function __construct(
        private readonly MailProviderRegistry $providers,
        private readonly SettingsService $settings,
    ) {}

    public function key(): string
    {
        return 'mail';
    }

    public function label(): string
    {
        return 'Mail provider';
    }

    public function isComplete(): bool
    {
        return $this->settings->get('mail_provider') !== null;
    }

    public function run(array $input): array
    {
        $provider = $input['provider'];

        if (! array_key_exists($provider, $this->providers->all())) {
            throw new \InvalidArgumentException("Unknown mail provider \"{$provider}\".");
        }

        $this->settings->set('mail_provider', $provider, group: 'mail');

        return ['provider' => $provider, 'available' => $this->providers->all()[$provider]->isAvailable()];
    }
}
