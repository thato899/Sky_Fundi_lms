<?php

declare(strict_types=1);

namespace Core\Installer\Application\Steps;

use Core\Installer\Contracts\InstallerStepInterface;
use Core\Settings\Application\SettingsService;

/**
 * Deliberately lightweight: Core\Licensing's License model is built
 * around a licensee (see core/Licensing/README.md), which today means
 * an Organization — and no tenant/organization model exists yet (see
 * docs/architecture/multi-tenancy.md). Until that exists, the
 * installer only records the license key the operator was issued;
 * binding it to a real License record happens once an organization
 * exists to hold it.
 */
final class LicenseStep implements InstallerStepInterface
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function key(): string
    {
        return 'license';
    }

    public function label(): string
    {
        return 'Platform license key';
    }

    public function isComplete(): bool
    {
        return $this->settings->get('platform_license_key') !== null;
    }

    public function run(array $input): array
    {
        $this->settings->set('platform_license_key', $input['license_key'], group: 'general', encrypted: true);

        return ['recorded' => true];
    }
}
