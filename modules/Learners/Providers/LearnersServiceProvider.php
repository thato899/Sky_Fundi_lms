<?php

declare(strict_types=1);

namespace Modules\Learners\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Modules\Learners\Http\Middleware\ResolveOrganizationLearner;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Learners\Policies\LearnerPolicy;

final class LearnersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->app->make(Router::class)->aliasMiddleware('learner.context', ResolveOrganizationLearner::class);
        Gate::policy(LearnerProfile::class, LearnerPolicy::class);

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/../routes/api.php');
    }
}
