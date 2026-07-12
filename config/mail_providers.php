<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Mail Provider Registry
|--------------------------------------------------------------------------
|
| Distinct from config/mail.php (Laravel's own required mailer
| transport configuration): this file is Core\Mail's registry of
| *provider* metadata — which of our MailProviderInterface adapters
| exists and which Laravel mailer it maps to. See core/Mail/README.md.
|
*/

return [
    'default_provider' => env('MAIL_DEFAULT_PROVIDER', 'smtp'),

    'providers' => [
        'smtp' => ['driver' => \Core\Mail\Infrastructure\Providers\SmtpMailProvider::class],
        'ses' => ['driver' => \Core\Mail\Infrastructure\Providers\SesMailProvider::class],
        'mailgun' => ['driver' => \Core\Mail\Infrastructure\Providers\MailgunMailProvider::class],
        // Placeholders — see core/Mail/README.md.
        'microsoft365' => ['driver' => \Core\Mail\Infrastructure\Providers\Microsoft365MailProvider::class],
        'google_workspace' => ['driver' => \Core\Mail\Infrastructure\Providers\GoogleWorkspaceMailProvider::class],
    ],
];
