<?php

declare(strict_types=1);

namespace Modules\Reports\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Modules\Reports\Http\Middleware\ResolveOrganizationReportResource;
use Modules\Reports\Infrastructure\Models\GradingScale;
use Modules\Reports\Infrastructure\Models\ReportCard;
use Modules\Reports\Infrastructure\Models\ReportCardTemplate;
use Modules\Reports\Infrastructure\Models\ReportingPeriod;
use Modules\Reports\Policies\ReportCardPolicy;
use Modules\Reports\Policies\ReportConfigurationPolicy;

final class ReportsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(resource_path('views/reports'), 'reports');
        $this->app->make(Router::class)->aliasMiddleware('report-resource.context', ResolveOrganizationReportResource::class);
        Gate::policy(ReportCard::class, ReportCardPolicy::class);
        Gate::policy(GradingScale::class, ReportConfigurationPolicy::class);
        Gate::policy(ReportingPeriod::class, ReportConfigurationPolicy::class);
        Gate::policy(ReportCardTemplate::class, ReportConfigurationPolicy::class);
        Route::middleware('api')->prefix('api/v1')->group(__DIR__.'/../routes/api.php');
        Route::middleware('web')->group(__DIR__.'/../routes/web.php');
    }
}
