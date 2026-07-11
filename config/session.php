<?php

declare(strict_types=1);

return [
    'driver' => env('SESSION_DRIVER', 'database'),
    'lifetime' => (int) env('SESSION_LIFETIME', 120),
    'expire_on_close' => (bool) env('SESSION_EXPIRE_ON_CLOSE', false),
    'encrypt' => (bool) env('SESSION_ENCRYPT', true),
    'files' => storage_path('framework/sessions'),
    'connection' => env('SESSION_CONNECTION'),
    'table' => env('SESSION_TABLE', 'sessions'),
    'store' => env('SESSION_STORE'),
    'lottery' => [2, 100],
    'cookie' => env('SESSION_COOKIE', 'sky_fundi_session'),
    'path' => '/',
    'domain' => env('SESSION_DOMAIN'),
    // Secure by default per docs/security/policies.md — must be forced
    // true in production even if APP_URL is briefly http during setup.
    'secure' => (bool) env('SESSION_SECURE_COOKIE', true),
    'http_only' => true,
    'same_site' => 'lax',
    'partitioned' => false,
];
