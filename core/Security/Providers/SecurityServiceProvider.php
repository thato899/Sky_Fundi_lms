<?php

declare(strict_types=1);

namespace Core\Security\Providers;

use Core\Auth\Events\UserLoggedIn;
use Core\Security\Listeners\DetectNewDeviceLogin;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

final class SecurityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/../routes/api.php');

        Event::listen(UserLoggedIn::class, DetectNewDeviceLogin::class);
    }
}
