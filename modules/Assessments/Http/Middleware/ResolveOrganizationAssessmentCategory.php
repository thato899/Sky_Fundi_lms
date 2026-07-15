<?php

declare(strict_types=1);

namespace Modules\Assessments\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Assessments\Infrastructure\Models\AssessmentCategory;
use Modules\Organizations\Infrastructure\Models\Organization;
use Symfony\Component\HttpFoundation\Response;

final class ResolveOrganizationAssessmentCategory
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = $request->attributes->get('organization');
        abort_unless($organization instanceof Organization, 403);
        $value = $request->route('category');
        if ($value instanceof AssessmentCategory) {
            abort_unless($value->getAttribute('organization_id') === $organization->getKey(), 404);

            return $next($request);
        }
        abort_unless(is_string($value), 404);
        $request->route()->setParameter('category', AssessmentCategory::query()->where('organization_id', $organization->getKey())->where('uuid', $value)->firstOrFail());

        return $next($request);
    }
}
