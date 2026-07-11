<?php

declare(strict_types=1);

use Core\Users\Infrastructure\Models\User;

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | 'web' backs Blade session-authenticated routes. 'sanctum' backs the
    | versioned REST API for all clients — web AJAX, Flutter, Android —
    | per docs/api/authentication.md: one authentication path for the API.
    |
    */
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'sanctum' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | The user model lives in Core\Users, not App\Models, per
    | docs/naming-conventions.md — Core::<Service>::<Layer> namespacing.
    |
    */
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    */
    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation / Timeout
    |--------------------------------------------------------------------------
    */
    'password_timeout' => 10800,
];
