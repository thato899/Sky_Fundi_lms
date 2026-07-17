<?php

declare(strict_types=1);

namespace Core\Api\Http\Middleware;

use Closure;
use Core\Analytics\Application\AnalyticsRecorder;
use Core\Analytics\Domain\Enums\AnalyticsMetric;
use Core\Logging\Application\PlatformLogger;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
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

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->logFailure($request, $exception, $this->durationMs($start));

            throw $exception;
        }

        try {
            $context = $this->requestContext($request, $this->durationMs($start)) + [
                'status' => $response->getStatusCode(),
            ];

            $slowThreshold = (int) config('observability.slow_request_ms', 1000);
            $isStreamed = $response instanceof StreamedResponse;
            $level = $slowThreshold > 0 && ! $isStreamed && $context['duration_ms'] >= $slowThreshold ? 'warning' : 'info';

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

    private function logFailure(Request $request, Throwable $exception, float $durationMs): void
    {
        try {
            $this->logger->application('error', 'http.request.failed', $this->requestContext($request, $durationMs) + [
                'outcome' => 'failed',
                'exception_class' => $exception::class,
            ]);
        } catch (Throwable) {
            // Logging must never replace or mask the original exception.
        }
    }

    /**
     * @return array{method: string, route: string|null, path: string, duration_ms: float}
     */
    private function requestContext(Request $request, float $durationMs): array
    {
        $route = $this->resolvedRoute($request);

        return [
            'method' => $request->method(),
            'route' => $route instanceof Route ? $route->getName() : null,
            'path' => $route instanceof Route ? $route->uri() : $request->path(),
            'duration_ms' => $durationMs,
        ];
    }

    private function resolvedRoute(Request $request): mixed
    {
        return $request->route();
    }

    private function durationMs(int $start): float
    {
        return round((hrtime(true) - $start) / 1_000_000, 2);
    }
}
