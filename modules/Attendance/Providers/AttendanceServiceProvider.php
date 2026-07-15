<?php

declare(strict_types=1);

namespace Modules\Attendance\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Modules\Attendance\Http\Middleware\ResolveOrganizationAttendance;
use Modules\Attendance\Infrastructure\Models\AttendanceSession;
use Modules\Attendance\Policies\AttendanceSessionPolicy;

final class AttendanceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->app->make(Router::class)->aliasMiddleware('attendance.context', ResolveOrganizationAttendance::class);
        Gate::policy(AttendanceSession::class, AttendanceSessionPolicy::class);
        Route::middleware('api')->prefix('api/v1')->group(__DIR__.'/../routes/api.php');
        Route::middleware('web')->group(__DIR__.'/../routes/web.php');
    }
}
