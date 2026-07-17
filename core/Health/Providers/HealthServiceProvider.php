<?php

declare(strict_types=1);

namespace Core\Health\Providers;

use Core\Health\Application\HealthCheckManager;
use Core\Health\Console\PlatformDiagnoseCommand;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

final class HealthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HealthCheckManager::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([PlatformDiagnoseCommand::class]);
        }

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/../routes/api.php');
    }
}
