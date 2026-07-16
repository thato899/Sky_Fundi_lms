<?php

declare(strict_types=1);

namespace Modules\Scheduling\Http\Controllers\Web;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Academics\Infrastructure\Models\CalendarEntry;
use Modules\Academics\Infrastructure\Models\TimetablePeriod;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Scheduling\Infrastructure\Models\Room;
use Modules\Scheduling\Infrastructure\Models\ScheduledLesson;
use Modules\Scheduling\Infrastructure\Models\TimetableTemplate;

final class SchedulingWebController
{
    public function dashboard(Request $request): mixed
    {
        Gate::authorize('viewAny', ScheduledLesson::class);
        $org = $this->org($request);
        $today = now($org->timezone)->toDateString();

        return view('scheduling.dashboard', ['organization' => $org, 'today' => ScheduledLesson::query()->where('organization_id', $org->id)->whereDate('lesson_date', $today)->count(), 'week' => ScheduledLesson::query()->where('organization_id', $org->id)->whereBetween('lesson_date', [now($org->timezone)->startOfWeek()->toDateString(), now($org->timezone)->endOfWeek()->toDateString()])->count(), 'cancelled' => ScheduledLesson::query()->where('organization_id', $org->id)->where('status', 'cancelled')->whereBetween('lesson_date', [now($org->timezone)->startOfWeek()->toDateString(), now($org->timezone)->endOfWeek()->toDateString()])->count(), 'periods' => TimetablePeriod::query()->where('organization_id', $org->id)->where('status', 'active')->count(), 'closures' => CalendarEntry::query()->where('organization_id', $org->id)->where('affects_teaching', true)->whereDate('end_date', '>=', $today)->orderBy('start_date')->limit(5)->get(), 'template' => TimetableTemplate::query()->where('organization_id', $org->id)->where('status', 'active')->first()]);
    }

    public function timetable(Request $request): mixed
    {
        Gate::authorize('viewAny', ScheduledLesson::class);
        $org = $this->org($request);
        $date = $request->filled('date') ? $request->date('date') : now($org->timezone);
        abort_unless($date !== null, 422);
        $mode = $request->query('view') === 'day' ? 'day' : 'week';
        $from = $mode === 'day' ? $date->toDateString() : $date->startOfWeek()->toDateString();
        $to = $mode === 'day' ? $date->toDateString() : $date->endOfWeek()->toDateString();
        $q = ScheduledLesson::query();
        $q->where('organization_id', $org->id)->whereBetween('lesson_date', [$from, $to]);
        $q->with(['classGroup', 'grade', 'subject', 'room', 'staff']);
        foreach (['class_id', 'grade_id', 'subject_id', 'room_id', 'delivery_mode', 'status'] as $f) {
            $q->when($request->query($f), fn ($x, $v) => $x->where($f, $v));
        } $q->when($request->query('staff_id'), fn ($x, $v) => $x->whereHas('staff', fn ($s) => $s->whereKey($v)));

        return view('scheduling.timetable', ['organization' => $org, 'lessons' => $q->orderBy('starts_at')->paginate(50), 'from' => $from, 'to' => $to, 'mode' => $mode]);
    }

    public function rooms(Request $request): mixed
    {
        Gate::authorize('viewAny', Room::class);

        return view('scheduling.rooms', ['organization' => $this->org($request), 'rooms' => Room::query()->where('organization_id', $this->org($request)->id)->orderBy('name')->paginate(30)]);
    }

    public function templates(Request $request): mixed
    {
        Gate::authorize('viewAny', TimetableTemplate::class);

        return view('scheduling.templates', ['organization' => $this->org($request), 'templates' => TimetableTemplate::query()->where('organization_id', $this->org($request)->id)->withCount('entries')->latest()->paginate(30)]);
    }

    public function lessons(Request $request): mixed
    {
        return $this->timetable($request);
    }

    private function org(Request $request): Organization
    {
        $org = $request->attributes->get('organization');
        abort_unless($org instanceof Organization, 403);

        return $org;
    }
}
