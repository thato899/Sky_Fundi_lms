<?php

declare(strict_types=1);

namespace Core\Health\Providers;

use Core\Health\Application\HealthCheckManager;
use Core\Health\Console\PlatformDiagnoseCommand;
use Core\Health\Console\ValidateEnvironmentCommand;
use Core\Health\Http\Controllers\Api\V1\HealthController;
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
            $this->commands([
                PlatformDiagnoseCommand::class,
                ValidateEnvironmentCommand::class,
            ]);
        }

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/../routes/api.php');

        Route::middleware('api')->group(function (): void {
            Route::get('/health', [HealthController::class, 'index']);
            Route::get('/ready', [HealthController::class, 'index']);
        });
    }
}
