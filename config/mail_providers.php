<?php

declare(strict_types=1);
use Core\Mail\Infrastructure\Providers\GoogleWorkspaceMailProvider;
use Core\Mail\Infrastructure\Providers\MailgunMailProvider;
use Core\Mail\Infrastructure\Providers\Microsoft365MailProvider;
use Core\Mail\Infrastructure\Providers\SesMailProvider;
use Core\Mail\Infrastructure\Providers\SmtpMailProvider;

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
        'smtp' => ['driver' => SmtpMailProvider::class],
        'ses' => ['driver' => SesMailProvider::class],
        'mailgun' => ['driver' => MailgunMailProvider::class],
        // Placeholders — see core/Mail/README.md.
        'microsoft365' => ['driver' => Microsoft365MailProvider::class],
        'google_workspace' => ['driver' => GoogleWorkspaceMailProvider::class],
    ],
];
