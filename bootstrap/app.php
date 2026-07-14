<?php

declare(strict_types=1);

use Core\Api\Exceptions\ApiExceptionHandler;
use Core\Api\Http\Middleware\ForceJsonResponse;
use Core\Api\Http\Middleware\LogApiRequests;
use Core\Auth\Http\Middleware\CheckAccountLocked;
use Core\RBAC\Http\Middleware\EnsurePermission;
use Core\Security\Http\Middleware\EnforceIpRestriction;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        $middleware->api(append: [
            LogApiRequests::class,
            'throttle:api-default',
        ]);

        $middleware->alias([
            'account.not-locked' => CheckAccountLocked::class,
            'permission' => EnsurePermission::class,
            'ip-restriction' => EnforceIpRestriction::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        ApiExceptionHandler::register($exceptions);
    })
    ->create();
