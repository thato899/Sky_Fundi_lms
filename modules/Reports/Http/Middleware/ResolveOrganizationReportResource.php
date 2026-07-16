<?php

declare(strict_types=1);

namespace Modules\Reports\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Reports\Infrastructure\Models\GradingScale;
use Modules\Reports\Infrastructure\Models\ReportCard;
use Modules\Reports\Infrastructure\Models\ReportCardTemplate;
use Modules\Reports\Infrastructure\Models\ReportingPeriod;
use Symfony\Component\HttpFoundation\Response;

final class ResolveOrganizationReportResource
{
    private const MAP = ['scale' => GradingScale::class, 'period' => ReportingPeriod::class, 'template' => ReportCardTemplate::class, 'reportCard' => ReportCard::class];

    public function handle(Request $request, Closure $next): Response
    {
        $organization = $request->attributes->get('organization');
        abort_unless($organization instanceof Organization, 403);
        foreach (self::MAP as $parameter => $model) {
            $value = $request->route($parameter);
            if ($value === null) {
                continue;
            } if ($value instanceof $model) {
                abort_unless($value->organization_id === $organization->id, 404);

                continue;
            } abort_unless(is_string($value), 404);
            $request->route()->setParameter($parameter, $model::query()->where('organization_id', $organization->id)->where('uuid', $value)->firstOrFail());
        }

        return $next($request);
    }
}
