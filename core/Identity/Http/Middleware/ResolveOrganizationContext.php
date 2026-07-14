<?php

declare(strict_types=1);

namespace Core\Identity\Http\Middleware;

use Closure;
use Core\Identity\Application\OrganizationContextService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveOrganizationContext
{
    public function __construct(private readonly OrganizationContextService $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $membership = $this->context->fromRequest($request);
        if ($request->user() && $membership === null) {
            abort(403, 'An active organization membership is required.');
        }
        $request->attributes->set('organization_membership', $membership);
        $request->attributes->set('organization', $membership?->organization);

        return $next($request);
    }
}
