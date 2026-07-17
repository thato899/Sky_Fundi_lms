<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Learners\Infrastructure\Models\GuardianProfile;
use Symfony\Component\HttpFoundation\Response;

final class ResolveOrganizationGuardian
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = $request->attributes->get('organization');
        abort_unless($organization !== null, 403);
        $value = $request->route('guardian');
        if (is_string($value)) {
            $guardian = GuardianProfile::query()
                ->where('organization_id', $organization->getKey())
                ->where('uuid', $value)
                ->firstOrFail();
            $request->route()->setParameter('guardian', $guardian);
        }

        return $next($request);
    }
}
