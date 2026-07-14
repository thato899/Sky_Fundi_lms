<?php

declare(strict_types=1);
use Core\Installer\Application\Steps\AdministratorStep;
use Core\Installer\Application\Steps\AIProviderStep;
use Core\Installer\Application\Steps\ApplicationStep;
use Core\Installer\Application\Steps\BrandingStep;
use Core\Installer\Application\Steps\LicenseStep;
use Core\Installer\Application\Steps\LocalizationStep;
use Core\Installer\Application\Steps\MailStep;
use Core\Installer\Application\Steps\StorageStep;

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
        ApplicationStep::class,
        LocalizationStep::class,
        MailStep::class,
        StorageStep::class,
        AIProviderStep::class,
        BrandingStep::class,
        AdministratorStep::class,
        LicenseStep::class,
    ],
];
