<?php

declare(strict_types=1);

namespace Core\Identity\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

final class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->app->make(Router::class)->aliasMiddleware('organization.context', \Core\Identity\Http\Middleware\ResolveOrganizationContext::class);
        Route::middleware('api')->prefix('api/v1')->group(__DIR__.'/../routes/api.php');
    }
}
