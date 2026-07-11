<?php

declare(strict_types=1);

namespace Core\Storage\Providers;

use Core\Storage\Application\StorageManager;
use Core\Storage\Contracts\FileStorageInterface;
use Illuminate\Support\ServiceProvider;

final class StorageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StorageManager::class);

        $this->app->bind(
            FileStorageInterface::class,
            fn ($app) => $app->make(StorageManager::class)->disk(),
        );
    }
}
