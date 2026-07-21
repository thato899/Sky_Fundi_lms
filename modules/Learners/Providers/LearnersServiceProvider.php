<?php

declare(strict_types=1);

namespace Modules\Learners\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Modules\Learners\Http\Middleware\ResolveOrganizationGuardian;
use Modules\Learners\Http\Middleware\ResolveOrganizationLearner;
use Modules\Learners\Infrastructure\Models\GuardianProfile;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Learners\Policies\GuardianPolicy;
use Modules\Learners\Policies\LearnerPolicy;

final class LearnersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Case-sensitive checkouts track migrations under database/ and factories
        // under Database/; Windows-built images collapse both into Database/.
        $migrationsPath = is_dir(__DIR__.'/../database/migrations')
            ? __DIR__.'/../database/migrations'
            : __DIR__.'/../Database/migrations';
        $this->loadMigrationsFrom($migrationsPath);
        $this->app->make(Router::class)->aliasMiddleware('learner.context', ResolveOrganizationLearner::class);
        $this->app->make(Router::class)->aliasMiddleware('guardian.context', ResolveOrganizationGuardian::class);
        Gate::policy(LearnerProfile::class, LearnerPolicy::class);
        Gate::policy(GuardianProfile::class, GuardianPolicy::class);

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/../routes/api.php');
        Route::middleware('web')->group(__DIR__.'/../routes/web.php');
    }
}
