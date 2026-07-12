<?php

declare(strict_types=1);

namespace Core\Api\Http\Middleware;

use Closure;
use Core\Analytics\Application\AnalyticsRecorder;
use Core\Analytics\Domain\Enums\AnalyticsMetric;
use Core\Logging\Application\PlatformLogger;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applied globally to the `api` middleware group (see
 * bootstrap/app.php). Two distinct outputs, per docs/development/README.md's
 * distinction between logging and analytics:
 * - PlatformLogger's `application` channel gets a structured line per
 *   request (method, path, status, duration) for debugging/tracing.
 * - Core\Analytics gets one ApiRequest counter event per request, for
 *   the "API Requests" metric in the brief's Platform Analytics list.
 * Never blocks or fails the request — logging/metrics failures are
 * swallowed, not surfaced to the caller.
 */
final class LogApiRequests
{
    public function __construct(
        private readonly PlatformLogger $logger,
        private readonly AnalyticsRecorder $analytics,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $durationMs = (int) ((microtime(true) - $start) * 1000);

        try {
            $this->logger->application('info', 'api.request', [
                'method' => $request->method(),
                'path' => $request->path(),
                'status' => $response->getStatusCode(),
                'duration_ms' => $durationMs,
            ]);

            $this->analytics->record(AnalyticsMetric::ApiRequest, value: 1.0, metadata: [
                'method' => $request->method(),
                'path' => $request->path(),
                'status' => $response->getStatusCode(),
            ]);
        } catch (\Throwable) {
            // Logging/metrics must never break the response.
        }

        return $response;
    }
}
