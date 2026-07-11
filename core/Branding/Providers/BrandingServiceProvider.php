<?php

declare(strict_types=1);

namespace Core\Branding\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

final class BrandingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // No migrations of its own — branding is stored as Settings
        // rows (group "branding"), see Application\BrandingService.
        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/../routes/api.php');
    }
}
