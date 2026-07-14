<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Symfony\Component\HttpFoundation\Response;

final class ResolveOrganizationLearner
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = $request->attributes->get('organization');
        abort_unless($organization instanceof Organization, 403);

        $uuid = $request->route('learner');
        abort_unless(is_string($uuid), 404);

        $learner = LearnerProfile::query()
            ->where('organization_id', $organization->getKey())
            ->where('uuid', $uuid)
            ->firstOrFail();

        $request->route()->setParameter('learner', $learner);

        return $next($request);
    }
}
