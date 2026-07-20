<?php

declare(strict_types=1);

namespace Modules\Attendance\Application;

use Core\AuditLogs\Application\AuditLogService;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Academics\Infrastructure\Models\AcademicTerm;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Academics\Infrastructure\Models\TimetablePeriod;
use Modules\Attendance\Domain\Enums\AttendanceSessionStatus;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Infrastructure\Models\AttendanceEntry;
use Modules\Attendance\Infrastructure\Models\AttendanceSession;
use Modules\Learners\Domain\Enums\LearnerStatus;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Staff\Application\TeachingAssignmentService;
use Modules\Staff\Infrastructure\Models\StaffProfile;

final class AttendanceSessionService
{
    private const FIELDS = ['academic_year_id', 'academic_term_id', 'class_id', 'subject_id', 'timetable_period_id', 'staff_profile_id', 'session_date', 'start_time', 'end_time', 'session_type', 'title', 'notes'];

    public function __construct(private readonly AuditLogService $audit, private readonly TeachingAssignmentService $assignments) {}

    public function create(Organization $organization, User $actor, array $data): AttendanceSession
    {
        $this->validateContext((string) $organization->getKey(), $data);

        return DB::transaction(function () use ($organization, $actor, $data): AttendanceSession {
            $session = AttendanceSession::query()->create([...Arr::only($data, self::FIELDS), 'organization_id' => $organization->getKey(), 'status' => AttendanceSessionStatus::Draft, 'created_by' => $actor->getKey(), 'updated_by' => $actor->getKey()]);
            $learners = $this->eligibleLearners($session)->lockForUpdate()->get(['id']);
            foreach ($learners as $learner) {
                AttendanceEntry::query()->create(['organization_id' => $organization->getKey(), 'attendance_session_id' => $session->getKey(), 'learner_profile_id' => $learner->getKey(), 'status' => AttendanceStatus::NotRecorded]);
            }
            $this->audit->record('attendance.session_created', $session, after: ['organization_id' => $organization->getKey(), 'class_id' => $session->getAttribute('class_id'), 'eligible_count' => $learners->count()]);

            return $session->load('entries.learner');
        }, 3);
    }

    public function update(AttendanceSession $session, User $actor, array $data): AttendanceSession
    {
        if (! in_array($session->getAttribute('status'), [AttendanceSessionStatus::Draft, AttendanceSessionStatus::Open], true)) {
            throw new DomainException('Only draft or open sessions may be updated.');
        }
        $merged = [...$session->getAttributes(), ...$data];
        $this->validateContext((string) $session->getAttribute('organization_id'), $merged);
        if (isset($data['class_id']) && $data['class_id'] !== $session->getAttribute('class_id')) {
            throw new DomainException('The class cannot change after eligible learners are populated.');
        }
        $before = Arr::only($session->getAttributes(), self::FIELDS);
        $session->fill(Arr::only($data, self::FIELDS))->setAttribute('updated_by', $actor->getKey())->save();
        $this->audit->record('attendance.session_updated', $session, $before, Arr::only($session->getAttributes(), self::FIELDS));

        return $session->refresh();
    }

    public function finalize(AttendanceSession $session, User $actor): AttendanceSession
    {
        return DB::transaction(function () use ($session, $actor): AttendanceSession {
            /** @var AttendanceSession $locked */
            $locked = AttendanceSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            if (! in_array($locked->getAttribute('status'), [AttendanceSessionStatus::Draft, AttendanceSessionStatus::Open], true)) {
                throw new DomainException('Only editable sessions may be finalized.');
            }
            if ($locked->entries()->where('status', AttendanceStatus::NotRecorded->value)->exists()) {
                throw new DomainException('Every eligible learner must have a recorded attendance status before finalization.');
            }
            $locked->update(['status' => AttendanceSessionStatus::Finalized, 'finalized_at' => now(), 'finalized_by' => $actor->getKey(), 'updated_by' => $actor->getKey()]);
            $this->audit->record('attendance.session_finalized', $locked, after: ['organization_id' => $locked->getAttribute('organization_id'), 'entry_count' => $locked->entries()->count()]);

            return $locked->refresh();
        }, 3);
    }

    public function reopen(AttendanceSession $session, User $actor, string $reason): AttendanceSession
    {
        if ($session->getAttribute('status') !== AttendanceSessionStatus::Finalized) {
            throw new DomainException('Only finalized sessions may be reopened.');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('A reopening reason is required.');
        }
        $session->update(['status' => AttendanceSessionStatus::Open, 'reopened_at' => now(), 'reopened_by' => $actor->getKey(), 'reopen_reason' => $reason, 'updated_by' => $actor->getKey()]);
        $this->audit->record('attendance.session_reopened', $session, after: ['organization_id' => $session->getAttribute('organization_id'), 'reason_recorded' => true]);

        return $session->refresh();
    }

    public function cancel(AttendanceSession $session, User $actor): AttendanceSession
    {
        if ($session->getAttribute('status') === AttendanceSessionStatus::Finalized) {
            throw new DomainException('A finalized session must be reopened before cancellation.');
        }
        if ($session->getAttribute('status') === AttendanceSessionStatus::Cancelled) {
            throw new DomainException('The session is already cancelled.');
        }
        $session->update(['status' => AttendanceSessionStatus::Cancelled, 'updated_by' => $actor->getKey()]);
        $this->audit->record('attendance.session_cancelled', $session, after: ['organization_id' => $session->getAttribute('organization_id')]);

        return $session->refresh();
    }

    public function eligibleLearners(AttendanceSession $session)
    {
        return LearnerProfile::query()->where('organization_id', $session->getAttribute('organization_id'))->where('current_class_id', $session->getAttribute('class_id'))->whereIn('learner_status', [LearnerStatus::Admitted->value, LearnerStatus::Active->value]);
    }

    private function validateContext(string $organizationId, array $data): void
    {
        $this->owned(AcademicYear::class, $data['academic_year_id'] ?? null, $organizationId, 'academic year');
        $class = $this->owned(ClassGroup::class, $data['class_id'] ?? null, $organizationId, 'class');
        if ($class->getAttribute('academic_year_id') !== $data['academic_year_id']) {
            throw new DomainException('The class must belong to the selected academic year.');
        }
        if ($data['academic_term_id'] ?? null) {
            $term = $this->owned(AcademicTerm::class, $data['academic_term_id'], $organizationId, 'academic term');
            if ($term->getAttribute('academic_year_id') !== $data['academic_year_id']) {
                throw new DomainException('The term must belong to the selected academic year.');
            }
        }
        foreach ([[Subject::class, 'subject_id', 'subject'], [TimetablePeriod::class, 'timetable_period_id', 'timetable period']] as [$model, $key, $label]) {
            if ($data[$key] ?? null) {
                $this->owned($model, $data[$key], $organizationId, $label);
            }
        }
        if ($data['staff_profile_id'] ?? null) {
            $this->owned(StaffProfile::class, $data['staff_profile_id'], $organizationId, 'staff member');
            $this->assignments->assertStaffAssignment($organizationId, $data['staff_profile_id'], $data['class_id'] ?? null, $data['subject_id'] ?? null);
        }
    }

    private function owned(string $model, mixed $id, string $organizationId, string $label): Model
    {
        if (! is_string($id) || $id === '') {
            throw new DomainException("A valid {$label} is required.");
        }
        $record = $model::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->find($id);
        if (! $record instanceof Model) {
            throw new DomainException("The {$label} must belong to the active organization.");
        }

        return $record;
    }
}
