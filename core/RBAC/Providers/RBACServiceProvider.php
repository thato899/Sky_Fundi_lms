<?php

declare(strict_types=1);

namespace Core\RBAC\Providers;

use Core\RBAC\Application\PermissionService;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

final class RBACServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PermissionService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/../routes/api.php');

        // Every `$user->can('x')` / `@can('x')` / EnsurePermission check
        // resolves here — see docs/security/rbac.md#enforcement-points.
        // Returning null (not false) for non-User models lets Laravel
        // fall through to normal policy resolution instead of denying.
        Gate::before(function ($user, string $ability) {
            if (! $user instanceof User) {
                return null;
            }

            return $this->app->make(PermissionService::class)->check($user, $ability) ? true : null;
        });
    }
}
