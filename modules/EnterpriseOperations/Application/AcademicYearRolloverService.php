<?php

declare(strict_types=1);

namespace Modules\EnterpriseOperations\Application;

use Core\AuditLogs\Application\AuditLogService;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Academics\Application\AcademicYearService;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\EnterpriseOperations\Infrastructure\Models\EnterpriseOperationRun;
use Modules\Learners\Infrastructure\Models\LearnerEnrolment;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;

/**
 * Builds an immutable rollover preview before any placement changes, then
 * applies exactly that approved preview inside one transaction. Source rows
 * remain historical evidence; destination rows are stamped with the run id.
 */
final class AcademicYearRolloverService
{
    public const TYPE = 'academic_year_rollover';

    public function __construct(
        private readonly EnterpriseOperationService $operations,
        private readonly AcademicYearService $academicYears,
        private readonly AuditLogService $audit,
    ) {}

    /** @param array<string, mixed> $configuration */
    public function dryRun(Organization $organization, User $actor, array $configuration): EnterpriseOperationRun
    {
        [$source, $destination] = $this->years($organization, $configuration);
        $rows = LearnerProfile::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->getKey())
            ->where('current_academic_year_id', $source->getKey())
            ->orderBy('last_name')->orderBy('first_name')->get();

        $outcomes = $rows->map(fn (LearnerProfile $learner): array => $this->propose($learner, $source, $destination, $configuration))->all();
        $summary = $this->summary($outcomes);
        $preview = ['source_year_id' => $source->getKey(), 'destination_year_id' => $destination->getKey(), 'generated_at' => now()->toIso8601String(), 'summary' => $summary, 'learners' => $outcomes];
        $run = $this->operations->preview($organization, self::TYPE, $configuration, $actor, $preview);
        $this->audit->record('enterprise.rollover.dry_run_completed', $run, after: ['summary' => $summary, 'source_year_id' => $source->getKey(), 'destination_year_id' => $destination->getKey()]);

        return $run;
    }

    /** @param array<string, mixed> $override */
    public function override(EnterpriseOperationRun $run, User $actor, string $learnerId, array $override): EnterpriseOperationRun
    {
        $this->assertType($run, ['pending_approval', 'draft']);
        $preview = $run->getAttribute('preview') ?? [];
        $learners = $preview['learners'] ?? [];
        foreach ($learners as &$outcome) {
            if (($outcome['learner_id'] ?? null) !== $learnerId) {
                continue;
            }
            $before = $outcome['proposed'] ?? null;
            $grade = $this->grade($run->getAttribute('organization_id'), $override['grade_id'] ?? null);
            $class = $this->class($run->getAttribute('organization_id'), $override['class_id'] ?? null, $grade?->getKey());
            if ($grade === null) {
                throw new DomainException('A manual placement requires an active destination grade in this organization.');
            }
            $outcome['proposed'] = ['grade_id' => $grade->getKey(), 'class_id' => $class?->getKey(), 'curriculum_id' => $grade->getAttribute('curriculum_id')];
            $outcome['status'] = $class === null ? 'warning' : 'ready';
            $outcome['reason'] = $class === null ? 'Destination class has not been assigned.' : null;
            $outcome['manual_override'] = ['by' => $actor->getKey(), 'at' => now()->toIso8601String(), 'reason' => $override['reason'] ?? null, 'original' => $before];
            break;
        }
        unset($outcome);
        $preview['learners'] = $learners;
        $preview['summary'] = $this->summary($learners);
        $run->update(['preview' => $preview]);
        $this->audit->record('enterprise.rollover.manual_override', $run, after: ['learner_id' => $learnerId, 'reason' => $override['reason'] ?? null]);

        return $run->refresh();
    }

    public function submit(EnterpriseOperationRun $run, User $actor): EnterpriseOperationRun
    {
        $this->assertType($run, ['pending_approval']);
        $summary = $run->getAttribute('preview')['summary'] ?? [];
        if (($summary['blocking_errors'] ?? 0) > 0) {
            throw new DomainException('Resolve all blocking errors before submitting this rollover.');
        }
        $this->audit->record('enterprise.rollover.submitted_for_approval', $run, after: ['summary' => $summary]);

        return $run;
    }

    public function approve(EnterpriseOperationRun $run, User $actor): EnterpriseOperationRun
    {
        if ($run->getAttribute('requested_by') === $actor->getKey()) {
            throw new DomainException('The requester may not approve an academic-year rollover.');
        }
        $approved = $this->operations->approve($run, $actor);
        $this->audit->record('enterprise.rollover.approved', $approved);

        return $approved;
    }

    public function execute(EnterpriseOperationRun $run, User $actor): EnterpriseOperationRun
    {
        $this->assertType($run, ['approved']);

        return DB::transaction(function () use ($run, $actor): EnterpriseOperationRun {
            $run = EnterpriseOperationRun::query()->lockForUpdate()->findOrFail($run->getKey());
            $this->assertType($run, ['approved']);
            $preview = $run->getAttribute('preview') ?? [];
            if (($preview['summary']['blocking_errors'] ?? 0) > 0) {
                throw new DomainException('This rollover contains unresolved blocking errors.');
            }
            $run->update(['status' => 'executing']);
            $created = [];
            foreach ($preview['learners'] ?? [] as $outcome) {
                if (! in_array($outcome['status'] ?? '', ['ready', 'warning'], true)) {
                    continue;
                }
                $learner = LearnerProfile::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($outcome['learner_id']);
                if ($learner->getAttribute('organization_id') !== $run->getAttribute('organization_id')) {
                    throw new DomainException('Tenant boundary violation.');
                }
                if (LearnerEnrolment::query()->where('learner_profile_id', $learner->getKey())->where('academic_year_id', $preview['destination_year_id'])->exists()) {
                    throw new DomainException('A destination enrolment already exists for '.$learner->getKey().'.');
                }
                $source = LearnerEnrolment::query()->where('learner_profile_id', $learner->getKey())->whereNull('ended_on')->lockForUpdate()->first();
                if ($source !== null) {
                    $source->update(['ended_on' => now()->toDateString()]);
                }
                $placement = $outcome['proposed'];
                $new = LearnerEnrolment::query()->create(['organization_id' => $run->getAttribute('organization_id'), 'learner_profile_id' => $learner->getKey(), 'academic_year_id' => $preview['destination_year_id'], 'grade_id' => $placement['grade_id'], 'class_id' => $placement['class_id'], 'curriculum_id' => $placement['curriculum_id'] ?? null, 'started_on' => now()->toDateString(), 'actor_id' => $actor->getKey(), 'enterprise_operation_run_id' => $run->getKey()]);
                $learner->update(['current_academic_year_id' => $preview['destination_year_id'], 'current_grade_id' => $placement['grade_id'], 'current_class_id' => $placement['class_id'], 'curriculum_id' => $placement['curriculum_id'] ?? null, 'updated_by' => $actor->getKey()]);
                $created[] = ['learner_id' => $learner->getKey(), 'enrolment_id' => $new->getKey(), 'source_enrolment_id' => $source?->getKey()];
            }
            if (($run->getAttribute('payload')['activate_destination'] ?? false) === true) {
                $this->academicYears->setCurrent(AcademicYear::query()->withoutGlobalScopes()->findOrFail($preview['destination_year_id']));
            }
            $completed = $this->operations->recordExecution($run, ['created' => $created, 'executed_by' => $actor->getKey(), 'count' => count($created)]);
            $this->audit->record('enterprise.rollover.execution_completed', $completed, after: ['count' => count($created)]);

            return $completed;
        }, 3);
    }

    public function rollback(EnterpriseOperationRun $run, User $actor): EnterpriseOperationRun
    {
        $this->assertType($run, ['executed']);

        return DB::transaction(function () use ($run, $actor): EnterpriseOperationRun {
            $generated = LearnerEnrolment::query()->where('enterprise_operation_run_id', $run->getKey())->lockForUpdate()->get();
            foreach ($generated as $row) {
                if ($row->getAttribute('updated_at')->greaterThan($run->getAttribute('executed_at')) || LearnerProfile::query()->withoutGlobalScopes()->find($row->getAttribute('learner_profile_id'))?->getAttribute('current_academic_year_id') !== $row->getAttribute('academic_year_id')) {
                    throw new DomainException('Rollback is blocked because a generated placement has subsequent changes.');
                }
            }
            foreach ($generated as $row) {
                $source = LearnerEnrolment::query()->find(collect($run->getAttribute('result')['created'] ?? [])->firstWhere('enrolment_id', $row->getKey())['source_enrolment_id'] ?? null);
                if ($source !== null) {
                    $source->update(['ended_on' => null]);
                }
                LearnerProfile::query()->withoutGlobalScopes()->whereKey($row->getAttribute('learner_profile_id'))->update(['current_academic_year_id' => $source?->getAttribute('academic_year_id'), 'current_grade_id' => $source?->getAttribute('grade_id'), 'current_class_id' => $source?->getAttribute('class_id'), 'curriculum_id' => $source?->getAttribute('curriculum_id'), 'updated_by' => $actor->getKey()]);
                $row->delete();
            }
            $run->update(['status' => 'rolled_back', 'rolled_back_at' => now(), 'result' => [...($run->getAttribute('result') ?? []), 'rollback_by' => $actor->getKey(), 'rolled_back_count' => $generated->count()]]);
            $this->audit->record('enterprise.rollover.rollback_completed', $run, after: ['count' => $generated->count()]);

            return $run->refresh();
        }, 3);
    }

    /** @return array{0: AcademicYear, 1: AcademicYear} */
    private function years(Organization $organization, array $configuration): array
    {
        $source = AcademicYear::query()->withoutGlobalScopes()->where('organization_id', $organization->getKey())->find($configuration['source_year_id'] ?? null);
        $destination = AcademicYear::query()->withoutGlobalScopes()->where('organization_id', $organization->getKey())->find($configuration['destination_year_id'] ?? null);
        if ($source === null || $destination === null || $source->is($destination)) {
            throw new DomainException('Select two different academic years belonging to this organization.');
        }

return [$source, $destination];
    }

    /** @return array<string, mixed> */
    private function propose(LearnerProfile $learner, AcademicYear $source, AcademicYear $destination, array $configuration): array
    {
        $current = $this->grade($learner->getAttribute('organization_id'), $learner->getAttribute('current_grade_id'));
        $rules = $configuration['rules'] ?? [];
        $rule = $rules[$current?->getKey()] ?? null;
        $proposedId = is_array($rule) ? ($rule['destination_grade_id'] ?? null) : $rule;
        $retained = is_array($rule) && (($rule['action'] ?? null) === 'retain');
        if ($retained) {
            $proposedId = $current?->getKey();
        } $grade = $this->grade($learner->getAttribute('organization_id'), $proposedId);
        $class = $grade === null ? null : ClassGroup::query()->withoutGlobalScopes()->where('organization_id', $learner->getAttribute('organization_id'))->where('academic_year_id', $destination->getKey())->where('grade_id', $grade->getKey())->where('status', 'active')->orderBy('name')->first();
        $status = $current === null || ($grade === null && ! $retained) ? 'blocked' : ($class === null ? 'warning' : 'ready');
        if ($rule === 'complete' || (is_array($rule) && ($rule['action'] ?? null) === 'complete')) {
            $status = 'completing';
            $grade = null;
            $class = null;
        }

return ['learner_id' => $learner->getKey(), 'learner_name' => trim($learner->getAttribute('first_name').' '.$learner->getAttribute('last_name')), 'current' => ['year_id' => $source->getKey(), 'grade_id' => $current?->getKey(), 'class_id' => $learner->getAttribute('current_class_id')], 'proposed' => ['grade_id' => $grade?->getKey(), 'class_id' => $class?->getKey(), 'curriculum_id' => $grade?->getAttribute('curriculum_id')], 'status' => $status, 'reason' => $status === 'blocked' ? 'A valid destination grade is required.' : ($status === 'warning' ? 'Destination class has not been assigned.' : null), 'retained' => $retained];
    }

    private function grade(string $organizationId, ?string $id): ?Grade
    {
        return $id === null ? null : Grade::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->where('status', 'active')->find($id);
    }

    private function class(string $organizationId, ?string $id, ?string $gradeId): ?ClassGroup
    {
        return $id === null ? null : ClassGroup::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->where('status', 'active')->when($gradeId, fn ($query) => $query->where('grade_id', $gradeId))->find($id);
    }

    /** @param array<int, array<string, mixed>> $outcomes @return array<string, int> */
    private function summary(array $outcomes): array
    {
        $counts = ['total' => count($outcomes), 'ready' => 0, 'warnings' => 0, 'blocking_errors' => 0, 'manual_review' => 0, 'retained' => 0, 'completing' => 0];
        foreach ($outcomes as $outcome) {
            $status = $outcome['status'] ?? '';
            if ($status === 'ready') {
                $counts['ready']++;
            } if ($status === 'warning') {
                $counts['warnings']++;
            } if ($status === 'blocked') {
                $counts['blocking_errors']++;
                $counts['manual_review']++;
            } if ($status === 'completing') {
                $counts['completing']++;
            } if (($outcome['retained'] ?? false) === true) {
                $counts['retained']++;
            }
        }

return $counts;
    }

    /** @param list<string> $allowed */
    private function assertType(EnterpriseOperationRun $run, array $allowed): void
    {
        if ($run->getAttribute('operation_type') !== self::TYPE || ! in_array($run->getAttribute('status'), $allowed, true)) {
            throw new DomainException('This rollover is not in a valid workflow state for that action.');
        }
    }
}
