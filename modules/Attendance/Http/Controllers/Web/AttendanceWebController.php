<?php

declare(strict_types=1);

namespace Modules\Attendance\Http\Controllers\Web;

use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Academics\Infrastructure\Models\AcademicTerm;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Academics\Infrastructure\Models\TimetablePeriod;
use Modules\Attendance\Application\AttendanceRecordingService;
use Modules\Attendance\Application\AttendanceSessionService;
use Modules\Attendance\Domain\Enums\AttendanceSessionStatus;
use Modules\Attendance\Domain\Enums\AttendanceSessionType;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Http\Requests\RecordAttendanceRequest;
use Modules\Attendance\Http\Requests\StoreAttendanceSessionRequest;
use Modules\Attendance\Infrastructure\Models\AttendanceEntry;
use Modules\Attendance\Infrastructure\Models\AttendanceSession;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Application\OrganizationService;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Staff\Infrastructure\Models\StaffProfile;

final class AttendanceWebController
{
    public function __construct(private readonly AttendanceSessionService $sessions, private readonly AttendanceRecordingService $recording, private readonly PermissionResolver $permissions, private readonly OrganizationService $organizations) {}

    public function index(Request $request): View
    {
        [$organization] = $this->context($request, 'attendance.view');
        $query = AttendanceSession::query()->where('organization_id', $organization->getKey())->with(['classGroup', 'subject', 'staffProfile'])->withCount(['entries', 'entries as recorded_count' => fn ($q) => $q->where('status', '!=', AttendanceStatus::NotRecorded->value)]);
        foreach (['academic_year_id', 'academic_term_id', 'class_id', 'subject_id', 'staff_profile_id', 'status', 'session_type'] as $filter) {
            $query->when($request->query($filter), fn ($q, $value) => $q->where($filter, $value));
        }
        $query->when($request->query('date_from'), fn ($q, $v) => $q->whereDate('session_date', '>=', $v))->when($request->query('date_to'), fn ($q, $v) => $q->whereDate('session_date', '<=', $v));
        $sessions = $query->orderByDesc('session_date')->orderByDesc('created_at')->paginate(20)->withQueryString();
        $today = AttendanceSession::query()->where('organization_id', $organization->getKey())->whereDate('session_date', today());
        $finalizedIds = AttendanceSession::query()->where('organization_id', $organization->getKey())->where('status', AttendanceSessionStatus::Finalized->value)->pluck('id');
        $totals = AttendanceEntry::query()->whereIn('attendance_session_id', $finalizedIds)->selectRaw('status, count(*) aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return view('attendance.index', $this->shared($request) + $this->options($organization) + ['sessions' => $sessions, 'todayCount' => $today->count(), 'counts' => AttendanceSession::query()->where('organization_id', $organization->getKey())->selectRaw('status, count(*) aggregate')->groupBy('status')->pluck('aggregate', 'status'), 'totals' => $totals]);
    }

    public function create(Request $request): View
    {
        [$organization] = $this->context($request, 'attendance.create');
        Gate::authorize('create', AttendanceSession::class);

        return view('attendance.form', $this->shared($request) + $this->options($organization));
    }

    public function store(StoreAttendanceSessionRequest $request): RedirectResponse
    {
        [$organization] = $this->context($request, 'attendance.create');
        try {
            $session = $this->sessions->create($organization, $this->actor($request), $request->validated());
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['session' => $e->getMessage()]);
        }

        return redirect()->route('attendance.register', $session->getAttribute('uuid'))->with('status', 'Attendance session created. Record the register to save attendance.');
    }

    public function show(Request $request, AttendanceSession $session): View
    {
        $this->context($request, 'attendance.view');
        Gate::authorize('view', $session);

        return view('attendance.register', $this->shared($request) + ['session' => $session->load(['classGroup', 'subject', 'entries.learner.currentClass']), 'statuses' => AttendanceStatus::cases()]);
    }

    public function register(RecordAttendanceRequest $request, AttendanceSession $session): RedirectResponse
    {
        $this->context($request, 'attendance.record');
        try {
            $this->recording->record($session, $this->actor($request), $request->validated('entries'));
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['register' => $e->getMessage()]);
        }

        return back()->with('status', 'Attendance register saved.');
    }

    public function finalize(Request $request, AttendanceSession $session): RedirectResponse
    {
        $this->context($request, 'attendance.finalize');
        Gate::authorize('finalize', $session);
        try {
            $this->sessions->finalize($session, $this->actor($request));
        } catch (DomainException $e) {
            return back()->withErrors(['finalize' => $e->getMessage()]);
        }

        return back()->with('status', 'Attendance session finalized and locked.');
    }

    public function reopen(Request $request, AttendanceSession $session): RedirectResponse
    {
        $this->context($request, 'attendance.reopen');
        Gate::authorize('reopen', $session);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->sessions->reopen($session, $this->actor($request), $validated['reason']);

        return back()->with('status', 'Attendance session reopened.');
    }

    public function cancel(Request $request, AttendanceSession $session): RedirectResponse
    {
        $this->context($request, 'attendance.cancel');
        Gate::authorize('cancel', $session);
        $this->sessions->cancel($session, $this->actor($request));

        return back()->with('status', 'Attendance session cancelled.');
    }

    public function learnerHistory(Request $request, mixed $learner): View
    {
        [$organization] = $this->context($request, 'attendance.view');
        abort_unless($learner instanceof LearnerProfile, 404);
        abort_unless($learner->getAttribute('organization_id') === $organization->getKey(), 404);
        $entries = AttendanceEntry::query()->where('organization_id', $organization->getKey())->where('learner_profile_id', $learner->getKey())->whereHas('session', fn ($q) => $q->where('status', 'finalized'))->with(['session.classGroup', 'session.subject'])->latest()->paginate(20);
        $totals = AttendanceEntry::query()->where('organization_id', $organization->getKey())->where('learner_profile_id', $learner->getKey())->whereHas('session', fn ($q) => $q->where('status', 'finalized'))->selectRaw('status, count(*) aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return view('attendance.history', $this->shared($request) + ['learner' => $learner, 'entries' => $entries, 'totals' => $totals]);
    }

    private function options(Organization $organization): array
    {
        $id = $organization->getKey();

        return ['years' => AcademicYear::query()->where('organization_id', $id)->orderByDesc('start_date')->get(), 'terms' => AcademicTerm::query()->where('organization_id', $id)->orderBy('start_date')->get(), 'classes' => ClassGroup::query()->where('organization_id', $id)->orderBy('name')->get(), 'subjects' => Subject::query()->where('organization_id', $id)->orderBy('name')->get(), 'periods' => TimetablePeriod::query()->where('organization_id', $id)->orderBy('order')->get(), 'staff' => StaffProfile::query()->where('organization_id', $id)->where('employment_status', 'active')->orderBy('last_name')->get(), 'sessionTypes' => AttendanceSessionType::cases(), 'sessionStatuses' => AttendanceSessionStatus::cases()];
    }

    private function context(Request $request, string $permission): array
    {
        $organization = $request->attributes->get('organization');
        $membership = $request->attributes->get('organization_membership');
        abort_unless($organization instanceof Organization && $membership instanceof Membership && $this->permissions->allows($membership, $permission), 403);

        return [$organization, $membership];
    }

    private function shared(Request $request): array
    {
        [$organization, $membership] = $this->context($request, 'attendance.view');

        return ['organization' => $organization, 'branding' => $this->organizations->branding($organization), 'permissions' => $this->permissions->permissions($membership)];
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
