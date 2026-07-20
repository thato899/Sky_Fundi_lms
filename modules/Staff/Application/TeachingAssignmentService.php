<?php

declare(strict_types=1);

namespace Modules\Staff\Application;

use Core\AuditLogs\Application\AuditLogService;
use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Organizations\Application\OrganizationService;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Staff\Infrastructure\Models\StaffProfile;
use Modules\Staff\Infrastructure\Models\TeachingAssignment;

/**
 * Maintains the date-ranged teacher-to-class/subject assignment timeline
 * (ADR-009) and answers assignment questions for consumer modules.
 * Enforcement is opt-in per organization through the Organizations settings
 * group `staff`, key `enforce_teaching_assignments`, so existing
 * organizations keep current behaviour until they enable it.
 */
final class TeachingAssignmentService
{
    public const SETTING_GROUP = 'staff';

    public const SETTING_KEY = 'enforce_teaching_assignments';

    public const BYPASS_PERMISSION = 'teaching_assignments.bypass';

    public function __construct(
        private readonly OrganizationService $organizations,
        private readonly PermissionResolver $permissions,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * @param  array{class_id?: string|null, subject_id?: string|null, academic_year_id?: string|null, started_on?: string|null}  $data
     */
    public function assign(Organization $organization, StaffProfile $staff, array $data, User $actor): TeachingAssignment
    {
        if ((string) $staff->getAttribute('organization_id') !== (string) $organization->getKey()) {
            throw new DomainException('The staff member must belong to the active organization.');
        }
        if (! in_array($staff->getAttribute('employment_status'), ['active', 'probation'], true)) {
            throw new DomainException('Inactive or terminated staff cannot receive teaching assignments.');
        }

        $class = $this->owned(ClassGroup::class, $data['class_id'] ?? null, $organization, 'class');
        $year = $this->owned(AcademicYear::class, $data['academic_year_id'] ?? null, $organization, 'academic year');
        if ($class->getAttribute('academic_year_id') !== $year->getKey()) {
            throw new DomainException('The class must belong to the selected academic year.');
        }
        $subjectId = $data['subject_id'] ?? null;
        if ($subjectId !== null) {
            $this->owned(Subject::class, $subjectId, $organization, 'subject');
        }

        return DB::transaction(function () use ($organization, $staff, $class, $year, $subjectId, $data, $actor): TeachingAssignment {
            /** @var EloquentCollection<int, TeachingAssignment> $openRows */
            $openRows = TeachingAssignment::query()
                ->where('staff_profile_id', $staff->getKey())
                ->where('class_id', $class->getKey())
                ->whereNull('ended_on')
                ->lockForUpdate()
                ->get();
            $duplicate = $openRows->contains(fn (TeachingAssignment $open): bool => $open->getAttribute('subject_id') === $subjectId);
            if ($duplicate) {
                throw new DomainException('An open assignment already exists for this staff member, class, and subject.');
            }

            $assignment = TeachingAssignment::query()->create([
                'organization_id' => $organization->getKey(),
                'staff_profile_id' => $staff->getKey(),
                'class_id' => $class->getKey(),
                'subject_id' => $subjectId,
                'academic_year_id' => $year->getKey(),
                'started_on' => $data['started_on'] ?? now()->toDateString(),
                'actor_id' => $actor->getKey(),
            ]);
            $this->audit->record('staff.teaching_assignment_created', $assignment, after: [
                'organization_id' => $organization->getKey(),
                'staff_profile_id' => $staff->getKey(),
                'class_id' => $class->getKey(),
                'subject_id' => $subjectId,
            ]);

            return $assignment;
        }, 3);
    }

    public function end(TeachingAssignment $assignment, User $actor): TeachingAssignment
    {
        if ($assignment->getAttribute('ended_on') !== null) {
            throw new DomainException('The teaching assignment has already ended.');
        }
        $assignment->setAttribute('ended_on', now()->toDateString());
        $assignment->setAttribute('actor_id', $actor->getKey());
        $assignment->save();
        $this->audit->record('staff.teaching_assignment_ended', $assignment, after: [
            'organization_id' => $assignment->getAttribute('organization_id'),
            'staff_profile_id' => $assignment->getAttribute('staff_profile_id'),
            'class_id' => $assignment->getAttribute('class_id'),
        ]);

        return $assignment->refresh();
    }

    public function enforced(Organization $organization): bool
    {
        $settings = $this->organizations->settings($organization);

        return (bool) ($settings[self::SETTING_GROUP][self::SETTING_KEY] ?? false);
    }

    /**
     * A subject-less assignment covers every subject in its class; a check
     * without a subject passes on any assignment for the class. Rows count
     * when open or when their date range covers the given date (today by
     * default).
     */
    public function isAssigned(string $organizationId, string $staffProfileId, string $classId, ?string $subjectId = null, ?string $onDate = null): bool
    {
        $date = $onDate ?? now()->toDateString();

        return TeachingAssignment::query()
            ->where('organization_id', $organizationId)
            ->where('staff_profile_id', $staffProfileId)
            ->where('class_id', $classId)
            ->whereDate('started_on', '<=', $date)
            ->where(fn ($query) => $query->whereNull('ended_on')->orWhereDate('ended_on', '>=', $date))
            ->when($subjectId !== null, fn ($query) => $query->where(
                fn ($subject) => $subject->whereNull('subject_id')->orWhere('subject_id', $subjectId)
            ))
            ->exists();
    }

    /**
     * Service-boundary guard for staffing references on assessments,
     * attendance sessions, and lessons. A no-op unless the organization has
     * opted into enforcement and both a staff member and class are present.
     */
    public function assertStaffAssignment(string $organizationId, mixed $staffProfileId, mixed $classId, mixed $subjectId = null): void
    {
        if (! is_string($staffProfileId) || $staffProfileId === '' || ! is_string($classId) || $classId === '') {
            return;
        }
        $organization = Organization::query()->find($organizationId);
        if (! $organization instanceof Organization || ! $this->enforced($organization)) {
            return;
        }
        $subject = is_string($subjectId) && $subjectId !== '' ? $subjectId : null;
        if (! $this->isAssigned($organizationId, $staffProfileId, $classId, $subject)) {
            throw new DomainException('The staff member is not assigned to teach this class and subject.');
        }
    }

    /**
     * Actor-level gate for teacher actions on a class: passes when the
     * organization has not opted in, when the membership carries the bypass
     * permission (administrators), or when the acting user's staff profile
     * holds a covering assignment.
     */
    public function actorMayActOn(Membership $membership, string $classId, ?string $subjectId = null): bool
    {
        $organization = $membership->getRelationValue('organization');
        if (! $organization instanceof Organization || ! $this->enforced($organization)) {
            return true;
        }
        if ($this->permissions->allows($membership, self::BYPASS_PERMISSION)) {
            return true;
        }

        /** @var StaffProfile|null $staff */
        $staff = StaffProfile::query()
            ->where('organization_id', $membership->getAttribute('organization_id'))
            ->where(fn ($query) => $query
                ->where('organization_membership_id', $membership->getKey())
                ->orWhere('user_id', $membership->getAttribute('user_id')))
            ->first();
        if ($staff === null) {
            return false;
        }

        return $this->isAssigned((string) $membership->getAttribute('organization_id'), (string) $staff->getKey(), $classId, $subjectId);
    }

    private function owned(string $model, mixed $id, Organization $organization, string $label): mixed
    {
        if (! is_string($id) || $id === '') {
            throw new DomainException("A valid {$label} is required.");
        }
        $record = $model::query()->withoutGlobalScopes()->where('organization_id', $organization->getKey())->find($id);
        if ($record === null) {
            throw new DomainException("The {$label} must belong to the active organization.");
        }

        return $record;
    }
}
