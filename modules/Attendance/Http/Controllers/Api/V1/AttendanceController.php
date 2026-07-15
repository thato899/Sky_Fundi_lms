<?php

declare(strict_types=1);

namespace Modules\Attendance\Http\Controllers\Api\V1;

use Core\AuditLogs\Application\AuditLogService;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Attendance\Application\AttendanceRecordingService;
use Modules\Attendance\Application\AttendanceSessionService;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Http\Requests\RecordAttendanceRequest;
use Modules\Attendance\Http\Requests\StoreAttendanceSessionRequest;
use Modules\Attendance\Http\Requests\UpdateAttendanceSessionRequest;
use Modules\Attendance\Http\Resources\AttendanceSessionResource;
use Modules\Attendance\Infrastructure\Models\AttendanceEntry;
use Modules\Attendance\Infrastructure\Models\AttendanceSession;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AttendanceController
{
    public function __construct(private readonly AttendanceSessionService $sessions, private readonly AttendanceRecordingService $recording, private readonly AuditLogService $audit) {}

    public function index(Request $request): mixed
    {
        Gate::authorize('viewAny', AttendanceSession::class);
        $query = AttendanceSession::query()->where('organization_id', $this->organization($request)->getKey())->with(['classGroup', 'subject'])->withCount(['entries', 'entries as recorded_count' => fn ($q) => $q->where('status', '!=', AttendanceStatus::NotRecorded->value)]);
        foreach (['academic_year_id', 'academic_term_id', 'class_id', 'subject_id', 'staff_profile_id', 'status', 'session_type'] as $filter) {
            $query->when($request->query($filter), fn ($q, $value) => $q->where($filter, $value));
        }
        $query->when($request->query('date_from'), fn ($q, $v) => $q->whereDate('session_date', '>=', $v))->when($request->query('date_to'), fn ($q, $v) => $q->whereDate('session_date', '<=', $v));

        return AttendanceSessionResource::collection($query->orderByDesc('session_date')->paginate(min(100, max(1, $request->integer('per_page', 20)))));
    }

    public function store(StoreAttendanceSessionRequest $request): AttendanceSessionResource
    {
        return new AttendanceSessionResource($this->sessions->create($this->organization($request), $this->actor($request), $request->validated())->load(['classGroup', 'subject', 'entries.learner']));
    }

    public function show(Request $request, AttendanceSession $session): AttendanceSessionResource
    {
        Gate::authorize('view', $session);

        return new AttendanceSessionResource($session->load(['classGroup', 'subject', 'entries.learner']));
    }

    public function update(UpdateAttendanceSessionRequest $request, AttendanceSession $session): AttendanceSessionResource
    {
        return new AttendanceSessionResource($this->sessions->update($session, $this->actor($request), $request->validated())->load(['classGroup', 'subject']));
    }

    public function register(RecordAttendanceRequest $request, AttendanceSession $session): AttendanceSessionResource
    {
        return new AttendanceSessionResource($this->recording->record($session, $this->actor($request), $request->validated('entries'))->load(['classGroup', 'subject', 'entries.learner']));
    }

    public function finalize(Request $request, AttendanceSession $session): AttendanceSessionResource
    {
        Gate::authorize('finalize', $session);

        return new AttendanceSessionResource($this->sessions->finalize($session, $this->actor($request))->load(['classGroup', 'subject']));
    }

    public function reopen(Request $request, AttendanceSession $session): AttendanceSessionResource
    {
        Gate::authorize('reopen', $session);
        $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return new AttendanceSessionResource($this->sessions->reopen($session, $this->actor($request), (string) $request->input('reason'))->load(['classGroup', 'subject']));
    }

    public function cancel(Request $request, AttendanceSession $session): AttendanceSessionResource
    {
        Gate::authorize('cancel', $session);

        return new AttendanceSessionResource($this->sessions->cancel($session, $this->actor($request))->load(['classGroup', 'subject']));
    }

    public function learnerHistory(Request $request, string $learner): JsonResponse
    {
        Gate::authorize('viewAny', AttendanceSession::class);
        $learnerProfile = LearnerProfile::query()->where('organization_id', $this->organization($request)->getKey())->where('uuid', $learner)->firstOrFail();
        $query = AttendanceEntry::query()->where('organization_id', $this->organization($request)->getKey())->where('learner_profile_id', $learnerProfile->getKey())->whereHas('session', fn ($q) => $q->where('status', 'finalized'))->with(['session.classGroup', 'session.subject']);
        $entries = $query->latest('created_at')->paginate(20);
        $totals = (clone $query)->selectRaw('status, count(*) aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return response()->json(['data' => $entries, 'summary_scope' => 'Recorded finalized sessions only', 'totals' => $totals]);
    }

    public function summary(Request $request): JsonResponse
    {
        Gate::authorize('viewReports', AttendanceSession::class);
        $organizationId = $this->organization($request)->getKey();
        $sessions = AttendanceSession::query()->where('organization_id', $organizationId)->where('status', 'finalized')->when($request->query('date_from'), fn ($q, $v) => $q->whereDate('session_date', '>=', $v))->when($request->query('date_to'), fn ($q, $v) => $q->whereDate('session_date', '<=', $v));
        foreach (['class_id', 'subject_id'] as $key) {
            $sessions->when($request->query($key), fn ($q, $v) => $q->where($key, $v));
        }
        $ids = (clone $sessions)->pluck('id');
        $entries = AttendanceEntry::query()->where('organization_id', $organizationId)->whereIn('attendance_session_id', $ids);

        return response()->json(['scope' => 'Recorded finalized sessions only', 'finalized_session_count' => $sessions->count(), 'learner_entry_count' => (clone $entries)->count(), 'status_totals' => (clone $entries)->selectRaw('status, count(*) aggregate')->groupBy('status')->pluck('aggregate', 'status'), 'late_minutes_total' => (int) (clone $entries)->sum('minutes_late')]);
    }

    public function export(Request $request, AttendanceSession $session): StreamedResponse
    {
        Gate::authorize('export', $session);
        $session->load(['classGroup', 'entries.learner']);
        $entries = $session->getRelation('entries');
        $status = $session->getAttribute('status');
        $this->audit->record('attendance.exported', $session, after: ['organization_id' => $session->getAttribute('organization_id'), 'entry_count' => $entries->count(), 'session_status' => $status->value]);

        return response()->streamDownload(function () use ($session): void {
            $out = fopen('php://output', 'wb');
            $entries = $session->getRelation('entries');
            $class = $session->getRelation('classGroup');
            $status = $session->getAttribute('status');
            fputcsv($out, ['Session status', $status->value]);
            fputcsv($out, ['Learner number', 'Learner name', 'Class', 'Status', 'Arrival time', 'Minutes late', 'Reason']);
            foreach ($entries as $entry) {
                $learner = $entry->getRelation('learner');
                fputcsv($out, array_map($this->csv(...), [$learner->getAttribute('learner_number'), trim($learner->getAttribute('first_name').' '.$learner->getAttribute('last_name')), $class->getAttribute('name'), $entry->getAttribute('status')->value, $entry->getAttribute('arrival_time'), $entry->getAttribute('minutes_late'), $entry->getAttribute('reason')]));
            }
            fclose($out);
        }, 'attendance-'.$session->getAttribute('uuid').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function csv(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function organization(Request $request): Organization
    {
        $organization = $request->attributes->get('organization');
        abort_unless($organization instanceof Organization, 403);

        return $organization;
    }
}
