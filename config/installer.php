<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Installer Steps
|--------------------------------------------------------------------------
|
| Order matters — steps run in this sequence. Every class must
| implement Core\Installer\Contracts\InstallerStepInterface. See
| core/Installer/README.md.
|
*/

return [
    'steps' => [
        \Core\Installer\Application\Steps\ApplicationStep::class,
        \Core\Installer\Application\Steps\LocalizationStep::class,
        \Core\Installer\Application\Steps\MailStep::class,
        \Core\Installer\Application\Steps\StorageStep::class,
        \Core\Installer\Application\Steps\AIProviderStep::class,
        \Core\Installer\Application\Steps\BrandingStep::class,
        \Core\Installer\Application\Steps\AdministratorStep::class,
        \Core\Installer\Application\Steps\LicenseStep::class,
    ],
];
