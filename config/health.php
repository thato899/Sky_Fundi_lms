<?php

declare(strict_types=1);
use Core\Health\Infrastructure\Checks\AIProviderHealthCheck;
use Core\Health\Infrastructure\Checks\ApiHealthCheck;
use Core\Health\Infrastructure\Checks\CacheHealthCheck;
use Core\Health\Infrastructure\Checks\DatabaseHealthCheck;
use Core\Health\Infrastructure\Checks\MailHealthCheck;
use Core\Health\Infrastructure\Checks\QueueHealthCheck;
use Core\Health\Infrastructure\Checks\StorageHealthCheck;

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
        ApiHealthCheck::class,
        DatabaseHealthCheck::class,
        CacheHealthCheck::class,
        QueueHealthCheck::class,
        StorageHealthCheck::class,
        MailHealthCheck::class,
        AIProviderHealthCheck::class,
    ],
];
