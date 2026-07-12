<?php

declare(strict_types=1);

namespace Core\Security\Http\Middleware;

use Closure;
use Core\Security\Application\IpRestrictionService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Exception\HttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Platform-scope IP allow/deny enforcement. Not applied globally by
 * default (see routes using it explicitly) — most deployments have no
 * restrictions configured, in which case IpRestrictionService::isAllowed()
 * returns true immediately. See core/Security/README.md.
 */
final class EnforceIpRestriction
{
    public function __construct(
        private readonly IpRestrictionService $restrictions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip() ?? '0.0.0.0';

        if (! $this->restrictions->isAllowed($ip)) {
            throw new HttpException(403, 'Access from this IP address is not permitted.');
        }

        return $next($request);
    }
}
