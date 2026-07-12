<?php

declare(strict_types=1);

namespace Core\Installer\Application\Steps;

use Core\Branding\Application\BrandingService;
use Core\Installer\Contracts\InstallerStepInterface;

final class BrandingStep implements InstallerStepInterface
{
    public function __construct(
        private readonly BrandingService $branding,
    ) {}

    public function key(): string
    {
        return 'branding';
    }

    public function label(): string
    {
        return 'Platform branding';
    }

    public function isComplete(): bool
    {
        // Branding always has a value (falls back to Sky Fundi
        // defaults — see Core\Branding\Application\BrandingService),
        // so this step is "complete" once explicitly run at least once.
        return app('cache')->has('installer:branding:done');
    }

    public function run(array $input): array
    {
        $branding = $this->branding->update(array_filter([
            'platform_name' => $input['platform_name'] ?? null,
            'support_email' => $input['support_email'] ?? null,
        ]));

        app('cache')->forever('installer:branding:done', true);

        return $branding;
    }
}
