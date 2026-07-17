<?php

declare(strict_types=1);

namespace Core\Api\Http\Middleware;

use Closure;
use Core\Analytics\Application\AnalyticsRecorder;
use Core\Analytics\Domain\Enums\AnalyticsMetric;
use Core\Logging\Application\PlatformLogger;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Applied globally to routed HTTP requests (see bootstrap/app.php).
 * Two distinct outputs, per docs/development/README.md's
 * distinction between logging and analytics:
 * - PlatformLogger's `application` channel gets a structured line per
 *   request (method, path, status, duration) for debugging/tracing.
 * - Core\Analytics gets one ApiRequest counter event per API request, for
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
        $start = hrtime(true);

        $response = $next($request);

        $durationMs = round((hrtime(true) - $start) / 1_000_000, 2);

        try {
            $route = $request->route();
            $context = [
                'method' => $request->method(),
                'route' => $route->getName(),
                'path' => $route->uri(),
                'status' => $response->getStatusCode(),
                'duration_ms' => $durationMs,
            ];

            $slowThreshold = (int) config('observability.slow_request_ms', 1000);
            $isStreamed = $response instanceof StreamedResponse;
            $level = $slowThreshold > 0 && ! $isStreamed && $durationMs >= $slowThreshold ? 'warning' : 'info';

            $this->logger->application($level, 'http.request.completed', $context + [
                'outcome' => $level === 'warning' ? 'slow' : 'completed',
            ]);

            if ($request->is('api/*')) {
                $this->analytics->record(AnalyticsMetric::ApiRequest, value: 1.0, metadata: $context);
            }
        } catch (Throwable) {
            // Logging/metrics must never break the response.
        }

        return $response;
    }
}
