<?php

declare(strict_types=1);

namespace Core\Mail\Providers;

use Core\Mail\Application\MailManager;
use Core\Mail\Application\MailProviderFactory;
use Core\Mail\Application\MailProviderRegistry;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

final class MailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MailProviderFactory::class);
        $this->app->singleton(MailProviderRegistry::class);
        $this->app->singleton(MailManager::class);
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/../routes/api.php');
    }
}
