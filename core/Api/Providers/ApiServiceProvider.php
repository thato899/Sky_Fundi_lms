<?php

declare(strict_types=1);

namespace Core\Api\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Defines the platform-wide named rate limiters — the "Rate Limiting"
 * leg of the API Gateway Foundation. Individual routes may still apply
 * a stricter, route-specific `throttle:N,M` (e.g. Core\Auth's login
 * endpoint) — these named limiters are the sane default for everything
 * that doesn't define its own. See core/Api/README.md.
 */
final class ApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('api-default', fn ($request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('api-sensitive', fn ($request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
    }
}
