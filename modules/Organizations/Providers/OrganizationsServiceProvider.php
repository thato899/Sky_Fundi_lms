<?php

declare(strict_types=1);

namespace Modules\Organizations\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Policies\OrganizationPolicy;

final class OrganizationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/organizations.php', 'organizations');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        Gate::policy(Organization::class, OrganizationPolicy::class);

        Route::middleware('api')->prefix('api/v1')->group(__DIR__.'/../routes/api.php');
    }
}
