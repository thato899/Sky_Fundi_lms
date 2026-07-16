<?php

declare(strict_types=1);

namespace Modules\Scheduling\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Scheduling\Infrastructure\Models\Room;
use Modules\Scheduling\Infrastructure\Models\ScheduledLesson;
use Modules\Scheduling\Infrastructure\Models\TimetableTemplate;
use Modules\Scheduling\Infrastructure\Models\TimetableTemplateEntry;
use Symfony\Component\HttpFoundation\Response;

final class ResolveSchedulingResource
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = $request->attributes->get('organization');
        abort_unless($organization instanceof Organization, 403);
        foreach (['room' => Room::class, 'template' => TimetableTemplate::class, 'entry' => TimetableTemplateEntry::class, 'lesson' => ScheduledLesson::class] as $key => $model) {
            $value = $request->route($key);
            if ($value === null) {
                continue;
            }
            if ($value instanceof $model) {
                abort_unless($value->organization_id === $organization->getKey(), 404);
            } else {
                abort_unless(is_string($value), 404);
                $request->route()->setParameter($key, $model::query()->where('organization_id', $organization->getKey())->where('uuid', $value)->firstOrFail());
            }
        }

        return $next($request);
    }
}
