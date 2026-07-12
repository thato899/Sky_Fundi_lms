<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Health Checks
|--------------------------------------------------------------------------
|
| Every class listed here must implement
| Core\Health\Contracts\HealthCheckInterface. See core/Health/README.md.
| Order affects only the order results are returned in, not behaviour.
|
*/

return [
    'checks' => [
        \Core\Health\Infrastructure\Checks\ApiHealthCheck::class,
        \Core\Health\Infrastructure\Checks\DatabaseHealthCheck::class,
        \Core\Health\Infrastructure\Checks\CacheHealthCheck::class,
        \Core\Health\Infrastructure\Checks\QueueHealthCheck::class,
        \Core\Health\Infrastructure\Checks\StorageHealthCheck::class,
        \Core\Health\Infrastructure\Checks\MailHealthCheck::class,
        \Core\Health\Infrastructure\Checks\AIProviderHealthCheck::class,
    ],
];
