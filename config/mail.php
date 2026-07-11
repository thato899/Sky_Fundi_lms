<?php

declare(strict_types=1);

return [
    'default' => env('MAIL_MAILER', 'smtp'),

    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
        ],
        'log' => ['transport' => 'log', 'channel' => env('MAIL_LOG_CHANNEL')],
        'array' => ['transport' => 'array'],
    ],

    // Overridden at runtime for the "from" name/address by
    // Core\Branding\Application\BrandingService, which reads the
    // platform's configured support email — see core/Branding/README.md.
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'no-reply@skyfundi.app'),
        'name' => env('MAIL_FROM_NAME', 'Sky Fundi Platform'),
    ],
];
