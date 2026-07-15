<?php

declare(strict_types=1);

namespace Modules\Academics\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Modules\Academics\Http\Middleware\EnforceAcademicOrganization;

/**
 * Bootstraps the Academics module exactly as every Core service
 * bootstraps itself (loadMigrationsFrom + a routes/api.php group) —
 * see modules/Academics/README.md for why this ServiceProvider, not
 * Core\Modules\Application\ModuleManager's database registry, is what
 * actually makes the module's code run. The registry (module.json +
 * the `modules` table) tracks entitlement/enablement as data; this
 * provider is what Laravel boots.
 */
final class AcademicsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(Router::class)->aliasMiddleware('academics.organization', EnforceAcademicOrganization::class);
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/../routes/api.php');
    }
}
