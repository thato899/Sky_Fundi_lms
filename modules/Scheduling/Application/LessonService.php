<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application;

use Carbon\CarbonImmutable;
use Core\AuditLogs\Application\AuditLogService;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Academics\Infrastructure\Models\AcademicTerm;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Attendance\Application\AttendanceSessionService;
use Modules\Attendance\Domain\Enums\AttendanceSessionType;
use Modules\Attendance\Infrastructure\Models\AttendanceSession;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Scheduling\Domain\Enums\LessonStatus;
use Modules\Scheduling\Infrastructure\Models\Room;
use Modules\Scheduling\Infrastructure\Models\ScheduleChangeLog;
use Modules\Scheduling\Infrastructure\Models\ScheduledLesson;
use Modules\Staff\Application\TeachingAssignmentService;
use Modules\Staff\Infrastructure\Models\StaffProfile;

final class LessonService
{
    private const FIELDS = ['academic_year_id', 'academic_term_id', 'timetable_template_entry_id', 'grade_id', 'class_id', 'subject_id', 'room_id', 'lesson_date', 'starts_at', 'ends_at', 'delivery_mode', 'title', 'lesson_objective', 'lesson_notes', 'rescheduled_from_lesson_id'];

    public function __construct(private readonly ScheduleConflictService $conflicts, private readonly AttendanceSessionService $attendance, private readonly AuditLogService $audit, private readonly TeachingAssignmentService $assignments) {}

    public function create(Organization $organization, User $actor, array $data, bool $override = false): ScheduledLesson
    {
        $normalized = $this->normalize($organization, $data);
        $found = $this->conflicts->lesson((string) $organization->getKey(), $normalized);
        if ($found && ! $override) {
            throw new DomainException('Scheduling conflicts must be resolved before creating the lesson.');
        }
        if ($found && trim((string) ($data['override_reason'] ?? '')) === '') {
            throw new DomainException('A conflict override reason is required.');
        }

        return DB::transaction(function () use ($organization, $actor, $normalized, $data, $found): ScheduledLesson {
            $lesson = ScheduledLesson::query()->create([...Arr::only($normalized, self::FIELDS), 'organization_id' => $organization->getKey(), 'status' => LessonStatus::Scheduled, 'created_by' => $actor->getKey(), 'updated_by' => $actor->getKey()]);
            foreach ($data['staff'] ?? [] as $assignment) {
                $this->assignStaff($lesson, $actor, $assignment);
            }
            $this->change($lesson, $actor, 'created', null, ['status' => 'scheduled'], $found ? (string) $data['override_reason'] : null);
            $this->audit->record($found ? 'scheduling.conflict_overridden' : 'scheduling.lesson_created', $lesson, after: ['organization_id' => $organization->getKey(), 'conflict_count' => count($found)]);

            return $lesson->refresh()->load('staff');
        }, 3);
    }

    public function assignStaff(ScheduledLesson $lesson, User $actor, array $assignment): ScheduledLesson
    {
        $staff = $this->owned(StaffProfile::class, $assignment['staff_profile_id'] ?? null, (string) $lesson->organization_id, 'staff member');
        if (! in_array($staff->getAttribute('employment_status'), ['active', 'probation'], true)) {
            throw new DomainException('Inactive or terminated staff cannot be assigned.');
        }
        if (($assignment['is_primary'] ?? false) && $lesson->staff()->wherePivot('is_primary', true)->exists()) {
            throw new DomainException('A lesson may have only one primary teacher.');
        }
        if ($lesson->staff()->whereKey($staff->getKey())->exists()) {
            throw new DomainException('The staff member is already assigned.');
        }
        $proposal = [...$lesson->getAttributes(), 'staff_ids' => [$staff->getKey()]];
        if ($this->conflicts->lesson((string) $lesson->organization_id, $proposal, (string) $lesson->getKey())) {
            throw new DomainException('The staff member has a scheduling conflict.');
        }
        $this->assignments->assertStaffAssignment((string) $lesson->organization_id, (string) $staff->getKey(), (string) $lesson->getAttribute('class_id'), $lesson->getAttribute('subject_id'));
        $lesson->staff()->attach($staff->getKey(), ['id' => (string) str()->uuid(), 'organization_id' => $lesson->organization_id, 'assignment_type' => $assignment['assignment_type'] ?? 'teacher', 'is_primary' => (bool) ($assignment['is_primary'] ?? false)]);
        $this->change($lesson, $actor, 'staff_assigned', null, ['staff_profile_id' => $staff->getKey()]);

        return $lesson->refresh()->load('staff');
    }

    public function cancel(ScheduledLesson $lesson, User $actor, string $reason): ScheduledLesson
    {
        if ($lesson->status === LessonStatus::Completed) {
            throw new DomainException('A completed lesson cannot be cancelled.');
        }
        if (trim($reason) === '') {
            throw new DomainException('A cancellation reason is required.');
        }
        $before = ['status' => $lesson->status->value];
        $lesson->update(['status' => LessonStatus::Cancelled, 'cancellation_reason' => $reason, 'updated_by' => $actor->getKey()]);
        $session = AttendanceSession::query()->where('scheduled_lesson_id', $lesson->getKey())->first();
        if ($session && $session->status->value !== 'finalized') {
            $this->attendance->cancel($session, $actor);
        }
        $this->change($lesson, $actor, 'cancelled', $before, ['status' => 'cancelled'], $reason);

        return $lesson->refresh();
    }

    public function reschedule(ScheduledLesson $lesson, Organization $organization, User $actor, array $data): ScheduledLesson
    {
        if ($lesson->status !== LessonStatus::Scheduled) {
            throw new DomainException('Only scheduled lessons may be rescheduled.');
        }
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            throw new DomainException('A rescheduling reason is required.');
        }

        return DB::transaction(function () use ($lesson, $organization, $actor, $data, $reason): ScheduledLesson {
            $replacement = $this->create($organization, $actor, [...Arr::only($lesson->getAttributes(), self::FIELDS), ...$data, 'rescheduled_from_lesson_id' => $lesson->getKey()]);
            foreach ($lesson->staff as $staff) {
                $pivot = $staff->getRelation('pivot');
                $this->assignStaff($replacement, $actor, ['staff_profile_id' => $staff->getKey(), 'assignment_type' => $pivot->getAttribute('assignment_type'), 'is_primary' => $pivot->getAttribute('is_primary')]);
            }
            $lesson->update(['status' => LessonStatus::Rescheduled, 'updated_by' => $actor->getKey()]);
            $this->change($lesson, $actor, 'rescheduled', ['status' => 'scheduled'], ['replacement_id' => $replacement->getKey()], $reason);

            return $replacement->refresh()->load('staff');
        }, 3);
    }

    public function complete(ScheduledLesson $lesson, User $actor, bool $missed = false, ?string $reason = null): ScheduledLesson
    {
        if ($lesson->status !== LessonStatus::Scheduled) {
            throw new DomainException('Only scheduled lessons may be completed or missed.');
        }
        if ($missed && trim((string) $reason) === '') {
            throw new DomainException('A missed lesson reason is required.');
        }
        if (! $missed && $lesson->starts_at->isFuture()) {
            throw new DomainException('A lesson cannot be completed before it starts.');
        }
        $status = $missed ? LessonStatus::Missed : LessonStatus::Completed;
        $lesson->update(['status' => $status, 'updated_by' => $actor->getKey()]);
        $this->change($lesson, $actor, $missed ? 'missed' : 'completed', ['status' => 'scheduled'], ['status' => $status->value], $reason);

        return $lesson->refresh();
    }

    public function createAttendance(ScheduledLesson $lesson, Organization $organization, User $actor): AttendanceSession
    {
        if ($lesson->status === LessonStatus::Cancelled || $lesson->status === LessonStatus::Rescheduled) {
            throw new DomainException('Cancelled or replaced lessons cannot create attendance sessions.');
        }
        $existing = AttendanceSession::query()->where('organization_id', $organization->getKey())->where('scheduled_lesson_id', $lesson->getKey())->first();
        if ($existing) {
            return $existing;
        }
        $session = $this->attendance->create($organization, $actor, ['academic_year_id' => $lesson->academic_year_id, 'academic_term_id' => $lesson->academic_term_id, 'class_id' => $lesson->class_id, 'subject_id' => $lesson->subject_id, 'session_date' => $lesson->lesson_date->toDateString(), 'start_time' => $lesson->starts_at->format('H:i:s'), 'end_time' => $lesson->ends_at->format('H:i:s'), 'session_type' => AttendanceSessionType::Subject, 'title' => $lesson->title]);
        $session->update(['scheduled_lesson_id' => $lesson->getKey()]);
        $this->change($lesson, $actor, 'attendance_created', null, ['attendance_session_id' => $session->getKey()]);

        return $session->refresh();
    }

    private function normalize(Organization $organization, array $data): array
    {
        $org = (string) $organization->getKey();
        /** @var AcademicYear $year */
        $year = $this->owned(AcademicYear::class, $data['academic_year_id'] ?? null, $org, 'academic year');
        $grade = $this->owned(Grade::class, $data['grade_id'] ?? null, $org, 'grade');
        /** @var ClassGroup $class */
        $class = $this->owned(ClassGroup::class, $data['class_id'] ?? null, $org, 'class');
        $this->owned(Subject::class, $data['subject_id'] ?? null, $org, 'subject');
        if ($class->grade_id !== $grade->getKey() || $class->academic_year_id !== $year->getKey()) {
            throw new DomainException('The class must match the selected grade and academic year.');
        }
        if ($data['academic_term_id'] ?? null) {
            /** @var AcademicTerm $term */
            $term = $this->owned(AcademicTerm::class, $data['academic_term_id'], $org, 'academic term');
            if ($term->academic_year_id !== $year->getKey()) {
                throw new DomainException('The term must belong to the selected year.');
            }
        }
        if ($data['room_id'] ?? null) {
            /** @var Room $room */
            $room = $this->owned(Room::class, $data['room_id'], $org, 'room');
            if (! $room->is_active) {
                throw new DomainException('Inactive rooms cannot host new lessons.');
            }
        }
        $timezone = (string) ($organization->timezone ?: config('app.timezone'));
        $date = (string) ($data['lesson_date'] ?? '');
        $starts = isset($data['starts_at']) && str_contains((string) $data['starts_at'], 'T') ? CarbonImmutable::parse($data['starts_at'], $timezone) : CarbonImmutable::parse($date.' '.($data['start_time'] ?? $data['starts_at'] ?? ''), $timezone);
        $ends = isset($data['ends_at']) && str_contains((string) $data['ends_at'], 'T') ? CarbonImmutable::parse($data['ends_at'], $timezone) : CarbonImmutable::parse($date.' '.($data['end_time'] ?? $data['ends_at'] ?? ''), $timezone);
        if ($ends->lessThanOrEqualTo($starts)) {
            throw new DomainException('Lesson end must be after its start.');
        }
        if ($starts->toDateString() < $year->start_date->toDateString() || $starts->toDateString() > $year->end_date->toDateString()) {
            throw new DomainException('The lesson must fall within the academic year.');
        }

        return [...$data, 'lesson_date' => $starts->toDateString(), 'starts_at' => $starts->utc(), 'ends_at' => $ends->utc()];
    }

    private function owned(string $model, mixed $id, string $org, string $label): Model
    {
        $record = is_string($id) ? $model::query()->withoutGlobalScopes()->where('organization_id', $org)->find($id) : null;
        if (! $record instanceof Model) {
            throw new DomainException("The {$label} must belong to the active organization.");
        }

        return $record;
    }

    private function change(ScheduledLesson $lesson, User $actor, string $action, ?array $before = null, ?array $after = null, ?string $reason = null): void
    {
        ScheduleChangeLog::query()->create(['organization_id' => $lesson->organization_id, 'scheduled_lesson_id' => $lesson->getKey(), 'action' => $action, 'before' => $before, 'after' => $after, 'reason' => $reason, 'changed_by' => $actor->getKey()]);
    }
}
