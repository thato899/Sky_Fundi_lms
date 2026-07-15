<?php

declare(strict_types=1);

namespace Modules\Assessments\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Modules\Assessments\Http\Middleware\ResolveOrganizationAssessment;
use Modules\Assessments\Http\Middleware\ResolveOrganizationAssessmentCategory;
use Modules\Assessments\Infrastructure\Models\Assessment;
use Modules\Assessments\Infrastructure\Models\AssessmentCategory;
use Modules\Assessments\Policies\AssessmentCategoryPolicy;
use Modules\Assessments\Policies\AssessmentPolicy;

final class AssessmentsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('assessment.context', ResolveOrganizationAssessment::class);
        $router->aliasMiddleware('assessment-category.context', ResolveOrganizationAssessmentCategory::class);
        Gate::policy(Assessment::class, AssessmentPolicy::class);
        Gate::policy(AssessmentCategory::class, AssessmentCategoryPolicy::class);
        Route::middleware('api')->prefix('api/v1')->group(__DIR__.'/../routes/api.php');
        Route::middleware('web')->group(__DIR__.'/../routes/web.php');
    }
}
