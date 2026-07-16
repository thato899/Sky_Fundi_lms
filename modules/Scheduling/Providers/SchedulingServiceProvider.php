<?php

declare(strict_types=1);

namespace Modules\Scheduling\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Modules\Scheduling\Http\Middleware\ResolveSchedulingResource;
use Modules\Scheduling\Infrastructure\Models\Room;
use Modules\Scheduling\Infrastructure\Models\ScheduledLesson;
use Modules\Scheduling\Infrastructure\Models\TimetableTemplate;
use Modules\Scheduling\Infrastructure\Models\TimetableTemplateEntry;
use Modules\Scheduling\Policies\SchedulingPolicy;

final class SchedulingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(resource_path('views/scheduling'), 'scheduling');
        $this->app->make(Router::class)->aliasMiddleware('scheduling.context', ResolveSchedulingResource::class);
        foreach ([Room::class, TimetableTemplate::class, TimetableTemplateEntry::class, ScheduledLesson::class] as $model) {
            Gate::policy($model, SchedulingPolicy::class);
        }
        Route::middleware('api')->prefix('api/v1')->group(__DIR__.'/../routes/api.php');
        Route::middleware('web')->group(__DIR__.'/../routes/web.php');
    }
}
