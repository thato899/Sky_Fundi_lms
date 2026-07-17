<?php

declare(strict_types=1);

namespace Core\Api\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) config('observability.request_id_header', 'X-Request-ID');
        $inbound = $request->headers->get($header);
        $requestId = is_string($inbound) && strlen($inbound) === 36 && Str::isUuid($inbound)
            ? strtolower($inbound)
            : (string) Str::uuid();

        $request->headers->set($header, $requestId);
        $request->attributes->set('request_id', $requestId);
        app()->instance('request_id', $requestId);

        $response = $next($request);
        $response->headers->set($header, $requestId);

        return $response;
    }
}
