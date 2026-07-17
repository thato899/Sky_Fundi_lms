<?php

declare(strict_types=1);

namespace App\Providers;

use Core\Logging\Application\PlatformLogger;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
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
