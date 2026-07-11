<?php

declare(strict_types=1);

return [
    'postmark' => ['token' => env('POSTMARK_TOKEN')],
    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Read by Core\Users\Application\UserService — kept here rather
    // than inline env() calls in application code, per
    // docs/development/coding-standards.md#laravel-conventions.
    'auth' => [
        'max_login_attempts' => (int) env('AUTH_MAX_LOGIN_ATTEMPTS', 5),
        'lockout_minutes' => (int) env('AUTH_LOCKOUT_MINUTES', 15),
        'password_expiry_days' => (int) env('AUTH_PASSWORD_EXPIRY_DAYS', 90),
    ],
];
