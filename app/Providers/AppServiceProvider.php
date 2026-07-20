<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\NavigationContext;
use Core\Logging\Application\PlatformLogger;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
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
        View::composer('layouts.web', function ($view): void {
            $user = auth()->guard()->user();
            $view->with('navigation', app(NavigationContext::class)->for(
                $user instanceof User ? $user : null,
                session('organization_id'),
            ));
        });

        $thresholdMs = (int) config('observability.slow_query_ms', 500);

        if ($thresholdMs <= 0) {
            return;
        }

        DB::listen(function (QueryExecuted $query) use ($thresholdMs): void {
            if ($query->time < $thresholdMs) {
                return;
            }

            try {
                app(PlatformLogger::class)->system('warning', 'database.query.slow', [
                    'duration_ms' => round($query->time, 2),
                    'connection' => $query->connectionName,
                    'query_signature' => hash('sha256', self::normalizeSql($query->sql)),
                ]);
            } catch (\Throwable) {
                // Observability must never interrupt database work.
            }
        });
    }

    private static function normalizeSql(string $sql): string
    {
        return strtolower(trim((string) preg_replace('/\s+/', ' ', $sql)));
    }
}
