<?php

declare(strict_types=1);

namespace Core\AIGateway\Providers;

use Core\AIGateway\Application\AIManager;
use Core\AIGateway\Application\ProviderFactory;
use Core\AIGateway\Application\ProviderRegistry;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

final class AIGatewayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProviderFactory::class);
        $this->app->singleton(ProviderRegistry::class);
        $this->app->singleton(AIManager::class);
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/../routes/api.php');
    }
}
