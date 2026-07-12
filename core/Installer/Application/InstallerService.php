<?php

declare(strict_types=1);

namespace Core\Installer\Application;

use Core\Installer\Application\DTOs\InstallationStatus;
use Core\Installer\Contracts\InstallerStepInterface;
use Core\Settings\Application\SettingsService;
use Illuminate\Contracts\Container\Container;

/**
 * Orchestrates the ordered installation workflow (config('installer.steps')
 * — see config/installer.php). Each step is independently idempotent
 * (InstallerStepInterface's docblock); this service adds nothing on
 * top except ordering, resolution, and the overall "is the platform
 * installed" flag. See core/Installer/README.md.
 */
final class InstallerService
{
    public function __construct(
        private readonly Container $container,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @return InstallerStepInterface[]
     */
    public function steps(): array
    {
        return array_map(
            fn (string $stepClass) => $this->container->make($stepClass),
            config('installer.steps', []),
        );
    }

    public function status(): InstallationStatus
    {
        $steps = array_map(fn (InstallerStepInterface $step) => [
            'key' => $step->key(),
            'label' => $step->label(),
            'complete' => $step->isComplete(),
        ], $this->steps());

        return new InstallationStatus(
            isInstalled: $this->isInstalled(),
            steps: $steps,
        );
    }

    public function runStep(string $key, array $input): array
    {
        $step = $this->findStep($key);
        $result = $step->run($input);

        if ($this->allStepsComplete()) {
            $this->markInstalled();
        }

        return $result;
    }

    public function isInstalled(): bool
    {
        return (bool) $this->settings->get('platform_installed', false);
    }

    private function allStepsComplete(): bool
    {
        foreach ($this->steps() as $step) {
            if (! $step->isComplete()) {
                return false;
            }
        }

        return true;
    }

    private function markInstalled(): void
    {
        $this->settings->set('platform_installed', true, group: 'general');
    }

    private function findStep(string $key): InstallerStepInterface
    {
        foreach ($this->steps() as $step) {
            if ($step->key() === $key) {
                return $step;
            }
        }

        throw new \InvalidArgumentException("Unknown installer step \"{$key}\".");
    }
}
