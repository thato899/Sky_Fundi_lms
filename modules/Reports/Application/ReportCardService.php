<?php

declare(strict_types=1);

namespace Modules\Reports\Application;

use Core\AuditLogs\Application\AuditLogService;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Learners\Domain\Enums\LearnerStatus;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Reports\Domain\Enums\ReportCardStatus;
use Modules\Reports\Domain\Enums\ReportingPeriodStatus;
use Modules\Reports\Infrastructure\Models\GradingScale;
use Modules\Reports\Infrastructure\Models\ReportCard;
use Modules\Reports\Infrastructure\Models\ReportCardTemplate;
use Modules\Reports\Infrastructure\Models\ReportingPeriod;

final class ReportCardService
{
    public function __construct(private readonly ReportCardCalculationService $calculator, private readonly AuditLogService $audit) {}

    public function generate(LearnerProfile $learner, ReportingPeriod $period, GradingScale $scale, ReportCardTemplate $template, User $actor, ?ReportCard $regenerate = null): ReportCard
    {
        return DB::transaction(function () use ($learner, $period, $scale, $template, $actor, $regenerate): ReportCard {
            /** @var LearnerProfile $lockedLearner */
            $lockedLearner = LearnerProfile::query()->whereKey($learner->getKey())->lockForUpdate()->firstOrFail();
            $organizationId = (string) $lockedLearner->getAttribute('organization_id');
            if (! in_array($lockedLearner->getAttribute('learner_status'), [LearnerStatus::Admitted, LearnerStatus::Active], true) || ! $lockedLearner->getAttribute('current_academic_year_id') || ! $lockedLearner->getAttribute('current_grade_id') || ! $lockedLearner->getAttribute('current_class_id')) {
                throw new DomainException('The learner is not eligible or has incomplete current placement.');
            }
            if ($period->organization_id !== $organizationId || $scale->organization_id !== $organizationId || $template->organization_id !== $organizationId || ! $scale->is_active || ! $template->is_active || ! in_array($period->status, [ReportingPeriodStatus::Open, ReportingPeriodStatus::Closed], true) || $period->academic_year_id !== $lockedLearner->getAttribute('current_academic_year_id')) {
                throw new DomainException('Period, scale, template, and learner placement must be valid in the active organization.');
            }
            if ($regenerate && ($regenerate->organization_id !== $organizationId || $regenerate->learner_profile_id !== $lockedLearner->getKey() || $regenerate->reporting_period_id !== $period->id)) {
                throw new DomainException('The report cannot be regenerated in this context.');
            }
            $replace = $regenerate && in_array($regenerate->status, [ReportCardStatus::Draft, ReportCardStatus::Generated], true);
            $calculation = $this->calculator->calculate($lockedLearner, $period->loadMissing('academicYear'), $scale->loadMissing('bands'));
            if ($replace) {
                /** @var ReportCard $card */
                $card = ReportCard::query()->whereKey($regenerate->id)->lockForUpdate()->firstOrFail();
                $card->subjects()->delete();
            } else {
                $version = ((int) ReportCard::query()->where('organization_id', $organizationId)->where('learner_profile_id', $lockedLearner->getKey())->where('reporting_period_id', $period->id)->lockForUpdate()->max('version_number')) + 1;
                $card = new ReportCard(['organization_id' => $organizationId, 'learner_profile_id' => $lockedLearner->getKey(), 'reporting_period_id' => $period->id, 'version_number' => $version]);
            }
            $card->fill(['academic_year_id' => $period->academic_year_id, 'academic_term_id' => $period->academic_term_id, 'report_card_template_id' => $template->id, 'class_id' => $lockedLearner->getAttribute('current_class_id'), 'grade_id' => $lockedLearner->getAttribute('current_grade_id'), 'grading_scale_id' => $scale->id, 'status' => ReportCardStatus::Generated, 'generated_at' => now(), 'generated_by' => $actor->getKey(), 'overall_average' => $calculation['overall_average'], ...$calculation['attendance'], 'snapshot_metadata' => ['calculation_version' => 1, 'attendance_basis' => 'recorded_finalized_sessions', 'branding_mode' => 'current_at_render']])->save();
            foreach ($calculation['subjects'] as $row) {
                $card->subjects()->create([...$row, 'organization_id' => $organizationId]);
            }
            $this->audit->record($replace ? 'reports.report_regenerated' : 'reports.report_generated', $card, after: ['organization_id' => $organizationId, 'version' => $card->version_number, 'subject_count' => count($calculation['subjects'])]);

            return $card->refresh()->load($this->relations());
        }, 3);
    }

    public function transition(ReportCard $card, User $actor, ReportCardStatus $to, ?string $reason = null): ReportCard
    {
        return DB::transaction(function () use ($card, $actor, $to, $reason): ReportCard {
            /** @var ReportCard $locked */
            $locked = ReportCard::query()->whereKey($card->id)->lockForUpdate()->firstOrFail();
            $allowed = match ($locked->status) {
                ReportCardStatus::Generated => [ReportCardStatus::UnderReview], ReportCardStatus::UnderReview => [ReportCardStatus::Approved], ReportCardStatus::Approved => [ReportCardStatus::Published], ReportCardStatus::Published => [ReportCardStatus::Withdrawn], default => []
            };
            if (! in_array($to, $allowed, true)) {
                throw new DomainException('Invalid report-card lifecycle transition.');
            }
            if ($to === ReportCardStatus::UnderReview && $locked->subjects()->count() === 0) {
                throw new DomainException('A report requires generated subject snapshots before review.');
            }
            if ($to === ReportCardStatus::Withdrawn && trim((string) $reason) === '') {
                throw new DomainException('A withdrawal reason is required.');
            }
            $action = ['under_review' => 'reviewed', 'approved' => 'approved', 'published' => 'published', 'withdrawn' => 'withdrawn'][$to->value];
            $locked->update(['status' => $to, $action.'_at' => now(), $action.'_by' => $actor->getKey(), 'withdrawal_reason' => $to === ReportCardStatus::Withdrawn ? trim((string) $reason) : $locked->withdrawal_reason]);
            $this->audit->record('reports.report_'.$to->value, $locked, after: ['organization_id' => $locked->organization_id, 'version' => $locked->version_number, 'reason_recorded' => $to === ReportCardStatus::Withdrawn]);

            return $locked->refresh()->load($this->relations());
        }, 3);
    }

    public function updateComments(ReportCard $card, User $actor, array $data): ReportCard
    {
        if (in_array($card->status, [ReportCardStatus::Published, ReportCardStatus::Withdrawn], true)) {
            throw new DomainException('Published report snapshots are immutable.');
        }
        if (array_key_exists('overall_comment', $data)) {
            $card->update(['overall_comment' => trim((string) $data['overall_comment']) ?: null]);
        }
        if (! empty($data['comment'])) {
            $card->comments()->create(['organization_id' => $card->organization_id, 'comment_type' => $data['comment_type'], 'comment' => trim($data['comment']), 'author_user_id' => $actor->getKey(), 'staff_profile_id' => $data['staff_profile_id'] ?? null]);
        }
        $this->audit->record('reports.comment_updated', $card, after: ['organization_id' => $card->organization_id, 'version' => $card->version_number]);

        return $card->refresh()->load($this->relations());
    }

    private function relations(): array
    {
        return ['learner', 'academicYear', 'academicTerm', 'period', 'template', 'gradingScale.bands', 'classGroup', 'grade', 'subjects', 'comments'];
    }
}
