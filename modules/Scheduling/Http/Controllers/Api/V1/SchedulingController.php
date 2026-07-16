<?php

declare(strict_types=1);

namespace Modules\Scheduling\Http\Controllers\Api\V1;

use Core\AuditLogs\Application\AuditLogService;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Scheduling\Application\LessonService;
use Modules\Scheduling\Application\ScheduleConflictService;
use Modules\Scheduling\Application\TimetableMaterializationService;
use Modules\Scheduling\Application\TimetableService;
use Modules\Scheduling\Infrastructure\Models\Room;
use Modules\Scheduling\Infrastructure\Models\ScheduledLesson;
use Modules\Scheduling\Infrastructure\Models\TimetableTemplate;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SchedulingController
{
    public function __construct(private readonly LessonService $lessons, private readonly TimetableService $templates, private readonly TimetableMaterializationService $materializer, private readonly ScheduleConflictService $conflicts, private readonly AuditLogService $audit) {}

    public function rooms(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Room::class);

        return response()->json(Room::query()->where('organization_id', $this->org($request)->getKey())->orderBy('name')->paginate(min(100, max(1, $request->integer('per_page', 20)))));
    }

    public function storeRoom(Request $request): JsonResponse
    {
        Gate::authorize('create', Room::class);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'code' => ['nullable', 'string', 'max:64'], 'location_type' => ['required', 'in:classroom,laboratory,hall,online,other'], 'capacity' => ['nullable', 'integer', 'min:1'], 'description' => ['nullable', 'string', 'max:2000'], 'online_url' => ['nullable', 'url', 'required_if:location_type,online', 'prohibited_unless:location_type,online']]);
        $room = Room::query()->create([...$data, 'organization_id' => $this->org($request)->getKey(), 'is_active' => true, 'created_by' => $this->actor($request)->getKey(), 'updated_by' => $this->actor($request)->getKey()]);
        $this->audit->record('scheduling.room_created', $room, after: ['organization_id' => $room->organization_id, 'location_type' => $room->location_type]);

        return response()->json(['data' => $room], 201);
    }

    public function showRoom(Request $request, Room $room): JsonResponse
    {
        Gate::authorize('view', $room);

        return response()->json(['data' => $room->makeHidden(['online_url'])]);
    }

    public function updateRoom(Request $request, Room $room): JsonResponse
    {
        Gate::authorize('update', $room);
        $room->update($request->validate(['name' => ['sometimes', 'string', 'max:255'], 'code' => ['nullable', 'string', 'max:64'], 'capacity' => ['nullable', 'integer', 'min:1'], 'description' => ['nullable', 'string', 'max:2000']]));
        $this->audit->record('scheduling.room_updated', $room, after: ['organization_id' => $room->organization_id]);

        return response()->json(['data' => $room->refresh()]);
    }

    public function toggleRoom(Request $request, Room $room, bool $active): JsonResponse
    {
        Gate::authorize('update', $room);
        $room->update(['is_active' => $active, 'updated_by' => $this->actor($request)->getKey()]);

        return response()->json(['data' => $room->refresh()]);
    }

    public function templateIndex(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', TimetableTemplate::class);

        return response()->json(TimetableTemplate::query()->where('organization_id', $this->org($request)->getKey())->with('entries')->latest()->paginate(20));
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        Gate::authorize('create', TimetableTemplate::class);
        $data = $request->validate(['academic_year_id' => ['required', 'uuid'], 'academic_term_id' => ['nullable', 'uuid'], 'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000'], 'effective_start_date' => ['required', 'date'], 'effective_end_date' => ['required', 'date']]);

        return response()->json(['data' => $this->templates->create($this->org($request), $this->actor($request), $data)], 201);
    }

    public function showTemplate(Request $request, TimetableTemplate $template): JsonResponse
    {
        Gate::authorize('view', $template);

        return response()->json(['data' => $template->load('entries')]);
    }

    public function addEntry(Request $request, TimetableTemplate $template): JsonResponse
    {
        Gate::authorize('update', $template);
        $data = $request->validate(['weekday' => ['required', 'integer', 'between:1,7'], 'teaching_period_id' => ['nullable', 'uuid'], 'start_time' => ['nullable', 'date_format:H:i'], 'end_time' => ['nullable', 'date_format:H:i'], 'grade_id' => ['required', 'uuid'], 'class_id' => ['required', 'uuid'], 'subject_id' => ['required', 'uuid'], 'room_id' => ['nullable', 'uuid'], 'delivery_mode' => ['required', 'in:in_person,online,hybrid'], 'notes' => ['nullable', 'string', 'max:2000'], 'display_order' => ['nullable', 'integer', 'min:0']]);

        return response()->json(['data' => $this->templates->addEntry($template, $data)], 201);
    }

    public function activate(Request $request, TimetableTemplate $template): JsonResponse
    {
        Gate::authorize('update', $template);

        return response()->json(['data' => $this->templates->activate($template, $this->actor($request))]);
    }

    public function materialize(Request $request, TimetableTemplate $template): JsonResponse
    {
        Gate::authorize('materialize', $template);
        $data = $request->validate(['start_date' => ['required', 'date'], 'end_date' => ['required', 'date']]);

        return response()->json(['data' => $this->materializer->materialize($template, $this->org($request), $this->actor($request), $data['start_date'], $data['end_date'])]);
    }

    public function lessonIndex(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ScheduledLesson::class);
        $q = $this->lessonQuery($request)->with(['classGroup', 'grade', 'subject', 'room', 'staff']);

        return response()->json($q->paginate(min(100, max(1, $request->integer('per_page', 20)))));
    }

    public function storeLesson(Request $request): JsonResponse
    {
        Gate::authorize('create', ScheduledLesson::class);
        $data = $this->lessonData($request);
        $override = $request->boolean('override_conflicts');
        if ($override) {
            Gate::authorize('overrideConflict', ScheduledLesson::class);
        }

        return response()->json(['data' => $this->lessons->create($this->org($request), $this->actor($request), $data, $override)], 201);
    }

    public function showLesson(Request $request, ScheduledLesson $lesson): JsonResponse
    {
        Gate::authorize('view', $lesson);

        return response()->json(['data' => $lesson->load(['classGroup', 'grade', 'subject', 'room', 'staff', 'changes'])]);
    }

    public function assignStaff(Request $request, ScheduledLesson $lesson): JsonResponse
    {
        Gate::authorize('assignStaff', $lesson);

        return response()->json(['data' => $this->lessons->assignStaff($lesson, $this->actor($request), $request->validate(['staff_profile_id' => ['required', 'uuid'], 'assignment_type' => ['required', 'in:teacher,assistant,substitute,observer'], 'is_primary' => ['boolean']]))]);
    }

    public function cancel(Request $request, ScheduledLesson $lesson): JsonResponse
    {
        Gate::authorize('cancel', $lesson);
        $d = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return response()->json(['data' => $this->lessons->cancel($lesson, $this->actor($request), $d['reason'])]);
    }

    public function reschedule(Request $request, ScheduledLesson $lesson): JsonResponse
    {
        Gate::authorize('reschedule', $lesson);

        return response()->json(['data' => $this->lessons->reschedule($lesson, $this->org($request), $this->actor($request), [...$this->lessonData($request, false), ...$request->validate(['reason' => ['required', 'string', 'max:1000']])])]);
    }

    public function complete(Request $request, ScheduledLesson $lesson, bool $missed = false): JsonResponse
    {
        Gate::authorize('complete', $lesson);
        $d = $missed ? $request->validate(['reason' => ['required', 'string', 'max:1000']]) : [];

        return response()->json(['data' => $this->lessons->complete($lesson, $this->actor($request), $missed, $d['reason'] ?? null)]);
    }

    public function attendance(Request $request, ScheduledLesson $lesson): JsonResponse
    {
        Gate::authorize('createAttendance', $lesson);

        return response()->json(['data' => $this->lessons->createAttendance($lesson, $this->org($request), $this->actor($request))]);
    }

    public function conflictIndex(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ScheduledLesson::class);
        $data = $this->lessonData($request);

        return response()->json(['data' => $this->conflicts->lesson((string) $this->org($request)->getKey(), $data)]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('export', ScheduledLesson::class);
        $from = $request->validate(['date_from' => ['required', 'date'], 'date_to' => ['required', 'date', 'after_or_equal:date_from', 'before_or_equal:'.now()->addDays(93)->toDateString()]]);
        $lessons = $this->lessonQuery($request)->with(['grade', 'classGroup', 'subject', 'room', 'staff'])->get();
        $this->audit->record('scheduling.timetable_exported', after: ['organization_id' => $this->org($request)->getKey(), 'row_count' => $lessons->count(), ...$from]);

        return response()->streamDownload(function () use ($lessons): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['Date', 'Start', 'End', 'Grade', 'Class', 'Subject', 'Primary teacher', 'Room', 'Delivery mode', 'Status']);
            foreach ($lessons as $l) {
                fputcsv($out, array_map($this->csv(...), [$l->lesson_date->toDateString(), $l->starts_at->format('H:i'), $l->ends_at->format('H:i'), $l->grade?->name, $l->classGroup?->name, $l->subject?->name, $l->staff->firstWhere('pivot.is_primary', true)?->first_name, $l->room?->name, $l->delivery_mode->value, $l->status->value]));
            } fclose($out);
        }, 'timetable.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function lessonQuery(Request $request)
    {
        $q = ScheduledLesson::query()->where('organization_id', $this->org($request)->getKey());
        foreach (['academic_year_id', 'academic_term_id', 'grade_id', 'class_id', 'subject_id', 'room_id', 'delivery_mode', 'status'] as $field) {
            $q->when($request->query($field), fn ($x, $v) => $x->where($field, $v));
        } $q->when($request->query('staff_id'), fn ($x, $v) => $x->whereHas('staff', fn ($s) => $s->whereKey($v)))->when($request->query('date_from'), fn ($x, $v) => $x->whereDate('lesson_date', '>=', $v))->when($request->query('date_to'), fn ($x, $v) => $x->whereDate('lesson_date', '<=', $v));
        $sort = in_array($request->query('sort'), ['lesson_date', 'starts_at', 'status'], true) ? $request->query('sort') : 'lesson_date';

        return $q->orderBy($sort)->orderBy('starts_at');
    }

    private function lessonData(Request $request, bool $full = true): array
    {
        $rules = ['lesson_date' => ['required', 'date'], 'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i', 'after:start_time'], 'room_id' => ['nullable', 'uuid'], 'delivery_mode' => ['required', 'in:in_person,online,hybrid'], 'title' => ['nullable', 'string', 'max:255'], 'lesson_objective' => ['nullable', 'string', 'max:3000'], 'lesson_notes' => ['nullable', 'string', 'max:5000'], 'staff' => ['array'], 'staff.*.staff_profile_id' => ['required', 'uuid'], 'staff.*.assignment_type' => ['required', 'in:teacher,assistant,substitute,observer'], 'staff.*.is_primary' => ['boolean'], 'override_reason' => ['nullable', 'string', 'max:1000']];
        if ($full) {
            $rules = [...$rules, 'academic_year_id' => ['required', 'uuid'], 'academic_term_id' => ['nullable', 'uuid'], 'grade_id' => ['required', 'uuid'], 'class_id' => ['required', 'uuid'], 'subject_id' => ['required', 'uuid']];
        }

        return $request->validate($rules);
    }

    private function csv(mixed $v): string
    {
        $v = (string) ($v ?? '');

        return preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v;
    }

    private function actor(Request $request): User
    {
        $u = $request->user();
        abort_unless($u instanceof User, 401);

        return $u;
    }

    private function org(Request $request): Organization
    {
        $o = $request->attributes->get('organization');
        abort_unless($o instanceof Organization, 403);

        return $o;
    }
}
