<?php

declare(strict_types=1);

namespace Modules\Materials\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Modules\Materials\Infrastructure\Models\Material;
use Modules\Materials\Policies\MaterialPolicy;

final class MaterialsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Case-sensitive checkouts track migrations under database/ and
        // factories under Database/ — see LearnersServiceProvider for
        // the precedent this mirrors.
        $migrationsPath = is_dir(__DIR__.'/../database/migrations')
            ? __DIR__.'/../database/migrations'
            : __DIR__.'/../Database/migrations';
        $this->loadMigrationsFrom($migrationsPath);

        Gate::policy(Material::class, MaterialPolicy::class);

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/../routes/api.php');
    }
}
