<?php

declare(strict_types=1);

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

return [
    'stateful' => explode(',', env(
        'SANCTUM_STATEFUL_DOMAINS',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1'
    )),

    'guard' => ['web'],

    // Personal access tokens expire per docs/security/policies.md#session-policy.
    // null means "no expiry" — every environment should set this explicitly.
    'expiration' => env('SANCTUM_TOKEN_EXPIRATION', 43200), // minutes (30 days)

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'skyfundi_'),

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],
];
