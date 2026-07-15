<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Modules\Academics\Infrastructure\Concerns\BelongsToOrganization;
use Modules\Organizations\Infrastructure\Models\Organization;
use Symfony\Component\HttpFoundation\Response;

final class EnforceAcademicOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = $request->attributes->get('organization');
        abort_unless($organization instanceof Organization, 403);

        foreach ($request->route()->parameters() as $parameter) {
            if ($parameter instanceof Model && in_array(BelongsToOrganization::class, class_uses_recursive($parameter), true)) {
                abort_unless($parameter->getAttribute('organization_id') === $organization->getKey(), 404);
            }
        }

        return $next($request);
    }
}
