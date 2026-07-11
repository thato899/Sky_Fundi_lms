<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Application-level bootstrapping that doesn't belong to any single Core
 * service. Kept intentionally thin — Core services register their own
 * bindings/routes/events in their own ServiceProviders (see
 * bootstrap/providers.php), per docs/architecture/module-system.md.
 */
final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
