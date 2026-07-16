<?php

declare(strict_types=1);

namespace Modules\Reports\Application;

use Core\Support\Exceptions\DomainException;
use Modules\Assessments\Domain\Enums\AssessmentResultStatus;
use Modules\Assessments\Infrastructure\Models\AssessmentResult;
use Modules\Attendance\Infrastructure\Models\AttendanceEntry;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Reports\Domain\Enums\SubjectResultStatus;
use Modules\Reports\Infrastructure\Models\GradingScale;
use Modules\Reports\Infrastructure\Models\ReportingPeriod;

final class ReportCardCalculationService
{
    public function calculate(LearnerProfile $learner, ReportingPeriod $period, GradingScale $scale): array
    {
        $organizationId = (string) $learner->getAttribute('organization_id');
        if ($period->organization_id !== $organizationId || $scale->organization_id !== $organizationId) {
            throw new DomainException('Report calculation context must share the active organization.');
        }
        $results = AssessmentResult::query()->where('assessment_results.organization_id', $organizationId)->where('learner_profile_id', $learner->getKey())
            ->whereHas('assessment', fn ($q) => $q->where('organization_id', $organizationId)->where('status', 'finalized')->where('academic_year_id', $period->academic_year_id)->when($period->academic_term_id, fn ($x, $term) => $x->where('academic_term_id', $term))->where('class_id', $learner->getAttribute('current_class_id'))->where('grade_id', $learner->getAttribute('current_grade_id'))->whereDate('assessment_date', '>=', $period->start_date)->whereDate('assessment_date', '<=', $period->result_cutoff_date ?? $period->end_date))
            ->with('assessment.subject')->get()->groupBy('assessment.subject_id');
        $subjects = [];
        $calculated = [];
        $order = 0;
        foreach ($results as $group) {
            $assessment = $group->first()->getRelation('assessment');
            $subject = $assessment->getRelation('subject');
            $marked = $group->filter(fn ($r) => $r->result_status === AssessmentResultStatus::Marked && $r->percentage !== null);
            $statuses = $group->pluck('result_status');
            $status = SubjectResultStatus::NoResults;
            $percentage = null;
            $totalWeight = null;
            if ($marked->isNotEmpty()) {
                $weighted = $marked->filter(fn ($r) => $r->getRelation('assessment')->getAttribute('weighting') !== null);
                $totalWeight = $weighted->sum(fn ($r) => (float) $r->getRelation('assessment')->getAttribute('weighting'));
                if ($weighted->isEmpty()) {
                    $percentage = round($marked->avg(fn ($r) => (float) $r->percentage), 2);
                    $status = SubjectResultStatus::Calculated;
                    $totalWeight = null;
                } elseif ($weighted->count() !== $marked->count() || $totalWeight < 99.999) {
                    $status = SubjectResultStatus::InsufficientData;
                } elseif ($totalWeight > 100.001) {
                    throw new DomainException('Eligible assessment weighting exceeds 100 percent for '.$subject->getAttribute('name').'.');
                } else {
                    $percentage = round($weighted->sum(fn ($r) => ((float) $r->percentage * (float) $r->getRelation('assessment')->getAttribute('weighting')) / 100), 2);
                    $status = SubjectResultStatus::Calculated;
                }
            } elseif ($statuses->every(fn ($s) => $s === AssessmentResultStatus::Exempt)) {
                $status = SubjectResultStatus::Exempt;
            }
            $band = $percentage === null ? null : $scale->bands->first(fn ($b) => (float) $percentage >= (float) $b->minimum_percentage && (float) $percentage <= (float) $b->maximum_percentage);
            if ($percentage !== null && ! $band) {
                throw new DomainException('The grading scale does not resolve percentage '.$percentage.'.');
            }
            $subjects[] = ['subject_id' => $subject->getKey(), 'subject_name_snapshot' => $subject->getAttribute('name'), 'subject_code_snapshot' => $subject->getAttribute('code'), 'marked_assessment_count' => $marked->count(), 'total_valid_weighting' => $totalWeight, 'calculated_percentage' => $percentage, 'grading_band_label' => $band?->label, 'grading_band_symbol' => $band?->symbol, 'subject_result_status' => $status, 'display_order' => $order++];
            if ($status === SubjectResultStatus::Calculated) {
                $calculated[] = $percentage;
            }
        }
        $attendanceQuery = AttendanceEntry::query()->where('attendance_entries.organization_id', $organizationId)->where('learner_profile_id', $learner->getKey())->whereHas('session', fn ($q) => $q->where('organization_id', $organizationId)->where('status', 'finalized')->whereDate('session_date', '>=', $period->start_date)->whereDate('session_date', '<=', $period->end_date));
        $attendance = (clone $attendanceQuery)->selectRaw('status, count(*) aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return ['subjects' => $subjects, 'overall_average' => $calculated === [] ? null : round(array_sum($calculated) / count($calculated), 2), 'attendance' => ['attendance_session_count' => (clone $attendanceQuery)->distinct('attendance_session_id')->count('attendance_session_id'), 'present_count' => (int) ($attendance['present'] ?? 0), 'absent_count' => (int) ($attendance['absent'] ?? 0), 'late_count' => (int) ($attendance['late'] ?? 0), 'excused_count' => (int) ($attendance['excused'] ?? 0), 'remote_count' => (int) ($attendance['remote'] ?? 0)]];
    }
}
