<?php

declare(strict_types=1);

namespace Core\Installer\Application\Steps;

use Core\AIGateway\Application\ProviderRegistry;
use Core\Installer\Contracts\InstallerStepInterface;
use Core\Settings\Application\SettingsService;

final class AIProviderStep implements InstallerStepInterface
{
    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly SettingsService $settings,
    ) {}

    public function key(): string
    {
        return 'ai_provider';
    }

    public function label(): string
    {
        return 'Default AI provider';
    }

    public function isComplete(): bool
    {
        return $this->settings->get('ai.default_provider') !== null;
    }

    public function run(array $input): array
    {
        $provider = $input['provider'];

        $this->settings->set('default_provider', $provider, group: 'ai');

        return ['provider' => $provider, 'available' => $this->providers->availableProviderNames()];
    }
}
