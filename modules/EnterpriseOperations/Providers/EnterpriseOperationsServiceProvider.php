<?php

declare(strict_types=1);

namespace Modules\EnterpriseOperations\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

final class EnterpriseOperationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
