<?php

declare(strict_types=1);

namespace Core\Api\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures every API request is treated as expecting JSON, so Laravel's
 * exception rendering and validation responses come back as JSON rather
 * than an HTML error page — see docs/api/error-handling.md.
 */
final class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        if ($request->isJson() && trim($request->getContent()) !== '') {
            try {
                json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return response()->json([
                    'error' => [
                        'code' => 'malformed_json',
                        'message' => 'The request body contains malformed JSON.',
                    ],
                ], 400)->header(
                    'X-Request-Id',
                    $request->header('X-Request-Id', (string) Str::uuid()),
                );
            }
        }

        return $next($request);
    }
}
