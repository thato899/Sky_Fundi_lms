<?php

declare(strict_types=1);

namespace Modules\Assessments\Application;

use Core\AuditLogs\Application\AuditLogService;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Academics\Infrastructure\Models\AcademicTerm;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Assessments\Domain\Enums\AssessmentResultStatus;
use Modules\Assessments\Domain\Enums\AssessmentStatus;
use Modules\Assessments\Domain\Enums\ResultReleaseStatus;
use Modules\Assessments\Infrastructure\Models\Assessment;
use Modules\Assessments\Infrastructure\Models\AssessmentCategory;
use Modules\Assessments\Infrastructure\Models\AssessmentResult;
use Modules\Learners\Domain\Enums\LearnerStatus;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Staff\Infrastructure\Models\StaffProfile;

final class AssessmentService
{
    private const FIELDS = ['academic_year_id', 'academic_term_id', 'grade_id', 'class_id', 'subject_id', 'assessment_category_id', 'staff_profile_id', 'title', 'description', 'assessment_date', 'due_date', 'maximum_mark', 'weighting', 'instructions'];

    public function __construct(private readonly AuditLogService $audit) {}

    public function create(Organization $organization, User $actor, array $data): Assessment
    {
        $this->validateContext((string) $organization->getKey(), $data);

        return DB::transaction(function () use ($organization, $actor, $data): Assessment {
            $assessment = Assessment::query()->create([...Arr::only($data, self::FIELDS), 'organization_id' => $organization->getKey(), 'status' => AssessmentStatus::Draft, 'result_release_status' => ResultReleaseStatus::Withheld, 'created_by' => $actor->getKey(), 'updated_by' => $actor->getKey()]);
            $learners = $this->eligibleLearners($assessment)->lockForUpdate()->get(['id']);
            foreach ($learners as $learner) {
                AssessmentResult::query()->create(['organization_id' => $organization->getKey(), 'assessment_id' => $assessment->getKey(), 'learner_profile_id' => $learner->getKey(), 'result_status' => AssessmentResultStatus::Pending]);
            }
            $this->audit->record('assessment.created', $assessment, after: ['organization_id' => $organization->getKey(), 'eligible_count' => $learners->count(), 'status' => 'draft']);

            return $assessment->load('results.learner');
        }, 3);
    }

    public function update(Assessment $assessment, User $actor, array $data): Assessment
    {
        $this->editable($assessment);
        if (isset($data['class_id']) && $data['class_id'] !== $assessment->getAttribute('class_id')) {
            throw new DomainException('The class cannot change after eligible learners are populated.');
        }
        $this->validateContext((string) $assessment->getAttribute('organization_id'), [...$assessment->getAttributes(), ...$data]);
        $assessment->fill(Arr::only($data, self::FIELDS))->setAttribute('updated_by', $actor->getKey())->save();
        $this->audit->record('assessment.updated', $assessment, after: ['organization_id' => $assessment->getAttribute('organization_id'), 'status' => $assessment->getAttribute('status')->value]);

        return $assessment->refresh();
    }

    public function finalize(Assessment $assessment, User $actor): Assessment
    {
        return DB::transaction(function () use ($assessment, $actor): Assessment {
            /** @var Assessment $locked */
            $locked = Assessment::query()->whereKey($assessment->getKey())->lockForUpdate()->firstOrFail();
            $this->editable($locked);
            /** @var Collection<int, AssessmentResult> $results */
            $results = $locked->results()->lockForUpdate()->get();
            if ($results->isEmpty() || $results->contains(fn (AssessmentResult $r) => $r->getAttribute('result_status') === AssessmentResultStatus::Pending)) {
                throw new DomainException('Every eligible learner must have a resolved result status before finalization.');
            }
            foreach ($results as $result) {
                if ($result->getAttribute('result_status') === AssessmentResultStatus::Marked && ($result->getAttribute('score') === null || (float) $result->getAttribute('score') > (float) $locked->getAttribute('maximum_mark'))) {
                    throw new DomainException('Every marked result must contain a valid score.');
                }
            }
            $locked->update(['status' => AssessmentStatus::Finalized, 'finalized_at' => now(), 'finalized_by' => $actor->getKey(), 'updated_by' => $actor->getKey()]);
            $this->audit->record('assessment.finalized', $locked, after: ['organization_id' => $locked->getAttribute('organization_id'), 'result_count' => $results->count()]);

            return $locked->refresh();
        }, 3);
    }

    public function reopen(Assessment $assessment, User $actor, string $reason): Assessment
    {
        if ($assessment->getAttribute('status') !== AssessmentStatus::Finalized) {
            throw new DomainException('Only finalized assessments may be reopened.');
        }
        if (trim($reason) === '') {
            throw new DomainException('A reopening reason is required.');
        }
        $assessment->update(['status' => AssessmentStatus::Open, 'result_release_status' => ResultReleaseStatus::Withheld, 'released_at' => null, 'released_by' => null, 'reopened_at' => now(), 'reopened_by' => $actor->getKey(), 'reopen_reason' => trim($reason), 'updated_by' => $actor->getKey()]);
        $this->audit->record('assessment.reopened', $assessment, after: ['organization_id' => $assessment->getAttribute('organization_id'), 'reason_recorded' => true]);

        return $assessment->refresh();
    }

    public function cancel(Assessment $assessment, User $actor): Assessment
    {
        if ($assessment->getAttribute('status') === AssessmentStatus::Finalized) {
            throw new DomainException('A finalized assessment must be reopened before cancellation.');
        }
        if ($assessment->getAttribute('status') === AssessmentStatus::Cancelled) {
            throw new DomainException('The assessment is already cancelled.');
        }
        $assessment->update(['status' => AssessmentStatus::Cancelled, 'updated_by' => $actor->getKey()]);
        $this->audit->record('assessment.cancelled', $assessment, after: ['organization_id' => $assessment->getAttribute('organization_id')]);

        return $assessment->refresh();
    }

    public function release(Assessment $assessment, User $actor, bool $released): Assessment
    {
        if ($assessment->getAttribute('status') !== AssessmentStatus::Finalized) {
            throw new DomainException('Only finalized assessment results may be released.');
        }
        $assessment->update(['result_release_status' => $released ? ResultReleaseStatus::Released : ResultReleaseStatus::Withheld, 'released_at' => $released ? now() : null, 'released_by' => $released ? $actor->getKey() : null, 'updated_by' => $actor->getKey()]);
        $this->audit->record($released ? 'assessment.results_released' : 'assessment.results_withheld', $assessment, after: ['organization_id' => $assessment->getAttribute('organization_id'), 'release_status' => $released ? 'released' : 'withheld']);

        return $assessment->refresh();
    }

    public function eligibleLearners(Assessment $assessment)
    {
        return LearnerProfile::query()->where('organization_id', $assessment->getAttribute('organization_id'))->where('current_class_id', $assessment->getAttribute('class_id'))->whereIn('learner_status', [LearnerStatus::Admitted->value, LearnerStatus::Active->value]);
    }

    private function editable(Assessment $assessment): void
    {
        if (! in_array($assessment->getAttribute('status'), [AssessmentStatus::Draft, AssessmentStatus::Open], true)) {
            throw new DomainException('Finalized or cancelled assessments cannot be changed.');
        }
    }

    private function validateContext(string $organizationId, array $data): void
    {
        $year = $this->owned(AcademicYear::class, $data['academic_year_id'] ?? null, $organizationId, 'academic year');
        $term = $this->owned(AcademicTerm::class, $data['academic_term_id'] ?? null, $organizationId, 'academic term');
        $grade = $this->owned(Grade::class, $data['grade_id'] ?? null, $organizationId, 'grade');
        $class = $this->owned(ClassGroup::class, $data['class_id'] ?? null, $organizationId, 'class');
        foreach ([[Subject::class, 'subject_id', 'subject'], [AssessmentCategory::class, 'assessment_category_id', 'category']] as [$model, $key, $label]) {
            $this->owned($model, $data[$key] ?? null, $organizationId, $label);
        }
        if ($data['staff_profile_id'] ?? null) {
            $this->owned(StaffProfile::class, $data['staff_profile_id'], $organizationId, 'staff member');
        }
        if ($term->getAttribute('academic_year_id') !== $year->getKey() || $grade->getAttribute('academic_year_id') !== $year->getKey() || $class->getAttribute('academic_year_id') !== $year->getKey() || $class->getAttribute('grade_id') !== $grade->getKey()) {
            throw new DomainException('The term, grade, and class must match the selected academic year and grade.');
        }
        $maximum = (float) ($data['maximum_mark'] ?? 0);
        if ($maximum <= 0) {
            throw new DomainException('The maximum mark must be greater than zero.');
        }
        if (isset($data['weighting']) && ((float) $data['weighting'] < 0 || (float) $data['weighting'] > 100)) {
            throw new DomainException('Weighting must be between zero and 100.');
        }
        if (($data['assessment_date'] ?? null) && ($data['due_date'] ?? null) && $data['due_date'] < $data['assessment_date']) {
            throw new DomainException('The due date must be on or after the assessment date.');
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
