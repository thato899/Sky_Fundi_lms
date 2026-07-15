<?php

declare(strict_types=1);

namespace Modules\Learners\Application;

use Core\AuditLogs\Application\AuditLogService;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Academics\Domain\Enums\AcademicStatus;
use Modules\Academics\Domain\Enums\AcademicYearStatus;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Curriculum;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Learners\Domain\Enums\LearnerStatus;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;

final class LearnerService
{
    private const PROFILE_FIELDS = [
        'admission_number', 'first_name', 'middle_name', 'last_name', 'preferred_name',
        'date_of_birth', 'admission_date', 'expected_completion_date', 'previous_institution',
        'language_of_instruction', 'home_language', 'learning_mode', 'learner_email',
        'learner_phone', 'residential_address', 'city', 'province', 'country', 'postal_code',
    ];

    private const PLACEMENT_FIELDS = [
        'current_academic_year_id', 'current_grade_id', 'current_class_id', 'curriculum_id',
    ];

    public function __construct(
        private readonly LearnerNumberService $numbers,
        private readonly LearnerStatusService $statuses,
        private readonly AuditLogService $audit,
    ) {}

    public function create(Organization $organization, User $actor, array $data, bool $allowManualNumber): LearnerProfile
    {
        return DB::transaction(function () use ($organization, $actor, $data, $allowManualNumber): LearnerProfile {
            $manualNumber = $data['learner_number'] ?? null;
            if ($manualNumber !== null && ! $allowManualNumber) {
                throw new DomainException('Manual learner numbers require learners.override_number.');
            }

            $this->validatePlacement($organization, $data);
            $learnerNumber = $manualNumber !== null
                ? $this->numbers->validateManual($organization, (string) $manualNumber)
                : $this->numbers->next($organization);

            try {
                $learner = LearnerProfile::query()->create([
                    ...Arr::only($data, [...self::PROFILE_FIELDS, ...self::PLACEMENT_FIELDS]),
                    'organization_id' => $organization->getKey(),
                    'learner_number' => $learnerNumber,
                    'user_id' => null,
                    'organization_membership_id' => null,
                    'portal_access_enabled' => false,
                    'created_by' => $actor->getKey(),
                    'updated_by' => $actor->getKey(),
                ]);
            } catch (QueryException $exception) {
                throw new DomainException('The learner number or admission number is already in use by this organization.', previous: $exception);
            }

            $this->audit->record('learners.created', $learner, after: $this->auditValues($learner));
            if ($manualNumber !== null) {
                $this->audit->record('learners.manual_number_used', $learner, after: ['learner_number' => $learnerNumber]);
            }

            return $learner->load(['currentAcademicYear', 'currentGrade', 'currentClass', 'curriculum']);
        }, 3);
    }

    public function update(LearnerProfile $learner, User $actor, array $data): LearnerProfile
    {
        return DB::transaction(function () use ($learner, $actor, $data): LearnerProfile {
            $before = $this->auditValues($learner);
            $learner->fill(Arr::only($data, self::PROFILE_FIELDS));
            $learner->setAttribute('updated_by', $actor->getKey())->save();
            $learner = $learner->refresh();
            $this->audit->record('learners.updated', $learner, $before, $this->auditValues($learner));

            return $learner->load(['currentAcademicYear', 'currentGrade', 'currentClass', 'curriculum']);
        }, 3);
    }

    public function updateAcademicPlacement(LearnerProfile $learner, User $actor, array $data): LearnerProfile
    {
        /** @var Organization $organization */
        $organization = $learner->getRelationValue('organization');
        $this->validatePlacement($organization, $data);

        return DB::transaction(function () use ($learner, $actor, $data): LearnerProfile {
            $before = Arr::only($learner->getAttributes(), self::PLACEMENT_FIELDS);
            $learner->fill(Arr::only($data, self::PLACEMENT_FIELDS));
            $learner->setAttribute('updated_by', $actor->getKey())->save();
            $learner = $learner->refresh();
            $this->audit->record('learners.academic_placement_updated', $learner, $before, Arr::only($learner->getAttributes(), self::PLACEMENT_FIELDS));

            return $learner->load(['currentAcademicYear', 'currentGrade', 'currentClass', 'curriculum']);
        }, 3);
    }

    public function transition(LearnerProfile $learner, User $actor, LearnerStatus $status, ?string $reason): LearnerProfile
    {
        return $this->statuses->transition($learner, $status, $actor, $reason);
    }

    public function archive(LearnerProfile $learner, User $actor, ?string $reason): LearnerProfile
    {
        return $this->statuses->archive($learner, $actor, $reason);
    }

    public function restore(LearnerProfile $learner, User $actor, ?string $reason): LearnerProfile
    {
        return $this->statuses->restore($learner, $actor, $reason);
    }

    private function validatePlacement(Organization $organization, array $data): void
    {
        /** @var AcademicYear|null $year */
        $year = isset($data['current_academic_year_id']) ? AcademicYear::query()->withoutGlobalScopes()->where('organization_id', $organization->getKey())->whereKey((string) $data['current_academic_year_id'])->first() : null;
        /** @var Grade|null $grade */
        $grade = isset($data['current_grade_id']) ? Grade::query()->withoutGlobalScopes()->where('organization_id', $organization->getKey())->whereKey((string) $data['current_grade_id'])->first() : null;
        /** @var ClassGroup|null $class */
        $class = isset($data['current_class_id']) ? ClassGroup::query()->withoutGlobalScopes()->where('organization_id', $organization->getKey())->whereKey((string) $data['current_class_id'])->first() : null;
        /** @var Curriculum|null $curriculum */
        $curriculum = isset($data['curriculum_id']) ? Curriculum::query()->withoutGlobalScopes()->where('organization_id', $organization->getKey())->whereKey((string) $data['curriculum_id'])->first() : null;

        if (isset($data['current_academic_year_id']) && ($year === null || $year->getAttribute('status') === AcademicYearStatus::Archived)) {
            throw new DomainException('The academic year must exist and must not be archived.');
        }
        if (isset($data['current_grade_id']) && ($grade === null || $grade->getAttribute('status') !== AcademicStatus::Active)) {
            throw new DomainException('The grade must exist and be active.');
        }
        if (isset($data['current_class_id']) && ($class === null || $class->getAttribute('status') !== AcademicStatus::Active)) {
            throw new DomainException('The class must exist and be active.');
        }
        if (isset($data['curriculum_id']) && ($curriculum === null || $curriculum->getAttribute('is_active') !== true)) {
            throw new DomainException('The curriculum must exist and be active.');
        }
        if ($class !== null && $grade !== null && $class->getAttribute('grade_id') !== $grade->getKey()) {
            throw new DomainException('The class must belong to the selected grade.');
        }
        if ($class !== null && $year !== null && $class->getAttribute('academic_year_id') !== $year->getKey()) {
            throw new DomainException('The class must belong to the selected academic year.');
        }
        if ($grade !== null && $year !== null && $grade->getAttribute('academic_year_id') !== null && $grade->getAttribute('academic_year_id') !== $year->getKey()) {
            throw new DomainException('The grade is not compatible with the selected academic year.');
        }
        if ($grade !== null && $curriculum !== null && $grade->getAttribute('curriculum_id') !== null && $grade->getAttribute('curriculum_id') !== $curriculum->getKey()) {
            throw new DomainException('The grade is not assigned to the selected curriculum.');
        }
    }

    private function auditValues(LearnerProfile $learner): array
    {
        return Arr::only($learner->getAttributes(), [
            'organization_id', 'learner_number', 'admission_number', 'learning_mode',
            'language_of_instruction', 'home_language',
        ]);
    }
}
