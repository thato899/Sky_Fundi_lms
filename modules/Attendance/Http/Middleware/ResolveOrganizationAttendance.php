<?php

declare(strict_types=1);

namespace Modules\Attendance\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Attendance\Infrastructure\Models\AttendanceSession;
use Modules\Organizations\Infrastructure\Models\Organization;
use Symfony\Component\HttpFoundation\Response;

final class ResolveOrganizationAttendance
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = $request->attributes->get('organization');
        abort_unless($organization instanceof Organization, 403);
        $uuid = $request->route('session');
        if ($uuid instanceof AttendanceSession) {
            abort_unless($uuid->getAttribute('organization_id') === $organization->getKey(), 404);

            return $next($request);
        }
        abort_unless(is_string($uuid), 404);
        $session = AttendanceSession::query()->where('organization_id', $organization->getKey())->where('uuid', $uuid)->firstOrFail();
        $request->route()->setParameter('session', $session);

        return $next($request);
    }
}
