<?php

declare(strict_types=1);

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [
    'default' => env('LOG_CHANNEL', 'stack'),
    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels — see docs/development/README.md#logging-strategy
    |--------------------------------------------------------------------------
    |
    | Separate channels per concern (application, ai, security,
    | authentication, system) so logs are filterable per
    | docs/development/README.md, without every Core service reinventing
    | its own logging setup — Core\Logging wraps these channels with
    | mandatory structured context (tenant, module, correlation id).
    |
    */
    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['application'],
            'ignore_exceptions' => false,
        ],

        'application' => [
            'driver' => 'daily',
            'path' => storage_path('logs/application.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
            'replace_placeholders' => true,
        ],

        'ai' => [
            'driver' => 'daily',
            'path' => storage_path('logs/ai.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
            'replace_placeholders' => true,
        ],

        'security' => [
            'driver' => 'daily',
            'path' => storage_path('logs/security.log'),
            'level' => 'info',
            'days' => 90,
            'replace_placeholders' => true,
        ],

        'authentication' => [
            'driver' => 'daily',
            'path' => storage_path('logs/authentication.log'),
            'level' => 'info',
            'days' => 90,
            'replace_placeholders' => true,
        ],

        'system' => [
            'driver' => 'daily',
            'path' => storage_path('logs/system.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
            'replace_placeholders' => true,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],
    ],
];
