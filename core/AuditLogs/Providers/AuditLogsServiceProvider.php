<?php

declare(strict_types=1);

namespace Core\AuditLogs\Providers;

use Core\AuditLogs\Listeners\AuditableEventSubscriber;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

final class AuditLogsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/../routes/api.php');

        Event::subscribe(AuditableEventSubscriber::class);
    }
}
