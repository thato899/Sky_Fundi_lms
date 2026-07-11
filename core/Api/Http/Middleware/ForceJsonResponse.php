<?php

declare(strict_types=1);

namespace Core\Api\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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

        return $next($request);
    }
}
