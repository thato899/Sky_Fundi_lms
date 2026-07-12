<?php

declare(strict_types=1);

namespace Core\Storage\Providers;

use Core\Storage\Application\StorageManager;
use Core\Storage\Application\StorageProviderFactory;
use Core\Storage\Application\StorageProviderRegistry;
use Core\Storage\Contracts\FileStorageInterface;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

final class StorageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StorageProviderFactory::class);
        $this->app->singleton(StorageProviderRegistry::class);
        $this->app->singleton(StorageManager::class);

        $this->app->bind(
            FileStorageInterface::class,
            fn ($app) => $app->make(StorageManager::class)->disk(),
        );
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/../routes/api.php');
    }
}
