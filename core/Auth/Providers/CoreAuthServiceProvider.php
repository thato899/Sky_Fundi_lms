<?php

declare(strict_types=1);

namespace Core\Auth\Providers;

use Core\Auth\Listeners\RevokeTokensOnAccountLock;
use Core\Users\Events\UserLocked;
use Core\Users\Events\UserSuspended;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

final class CoreAuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/../routes/api.php');

        Event::listen(UserSuspended::class, [RevokeTokensOnAccountLock::class, 'handleSuspended']);
        Event::listen(UserLocked::class, [RevokeTokensOnAccountLock::class, 'handleLocked']);
    }
}
