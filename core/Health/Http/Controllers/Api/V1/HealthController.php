<?php

declare(strict_types=1);

namespace Core\Health\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Health\Application\HealthCheckManager;
use Illuminate\Http\JsonResponse;

final class HealthController extends Controller
{
    public function __construct(
        private readonly HealthCheckManager $manager,
    ) {}

    /**
     * Public, unauthenticated, deliberately minimal — safe for a load
     * balancer or uptime monitor to poll frequently without exposing
     * internal details. Returns 200 for healthy/degraded, 503 for
     * unhealthy, so infrastructure-level health probes can act on the
     * HTTP status alone.
     */
    public function index(): JsonResponse
    {
        $results = $this->manager->runReadiness();
        $overall = $this->manager->overallStatus($results);

        return response()->json([
            'status' => $overall->value === 'unhealthy' ? 'not_ready' : 'ready',
            'checks' => array_map(static fn ($result): array => [
                'name' => $result->name,
                'status' => $result->status->value,
            ], $results),
        ], $overall->value === 'unhealthy' ? 503 : 200);
    }

    /**
     * Full per-check breakdown — gated by core.health.view since it
     * can reveal internal configuration details (which disk/provider
     * is default, latency figures, backlog counts).
     */
    public function detailed(): JsonResponse
    {
        $results = $this->manager->runAll();
        $overall = $this->manager->overallStatus($results);

        return response()->json([
            'status' => $overall->value,
            'checks' => array_map(fn ($result) => [
                'name' => $result->name,
                'status' => $result->status->value,
                'message' => $result->message,
                'meta' => $result->meta,
            ], $results),
            'checked_at' => now()->toIso8601String(),
        ], $overall->value === 'unhealthy' ? 503 : 200);
    }
}
