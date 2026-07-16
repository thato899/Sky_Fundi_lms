<?php

declare(strict_types=1);

namespace Modules\Reports\Application;

use Core\AuditLogs\Application\AuditLogService;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Academics\Infrastructure\Models\AcademicTerm;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Reports\Domain\Enums\ReportingPeriodStatus;
use Modules\Reports\Infrastructure\Models\GradingScale;
use Modules\Reports\Infrastructure\Models\ReportCardTemplate;
use Modules\Reports\Infrastructure\Models\ReportingPeriod;

final class ReportConfigurationService
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function saveScale(Organization $organization, User $actor, array $data, ?GradingScale $scale = null): GradingScale
    {
        return DB::transaction(function () use ($organization, $actor, $data, $scale): GradingScale {
            if ($scale && $scale->organization_id !== $organization->getKey()) {
                throw new DomainException('The grading scale must belong to the active organization.');
            }
            $bands = $data['bands'] ?? [];
            $this->validateBands($bands);
            $values = [...Arr::only($data, ['name', 'code', 'description', 'pass_threshold', 'is_active']), 'organization_id' => $organization->getKey(), 'updated_by' => $actor->getKey()];
            if ($scale) {
                $scale->update($values);
                $scale->bands()->delete();
            } else {
                $scale = GradingScale::query()->create([...$values, 'created_by' => $actor->getKey()]);
            }
            foreach ($bands as $i => $band) {
                $scale->bands()->create([...Arr::only($band, ['label', 'code', 'minimum_percentage', 'maximum_percentage', 'symbol', 'description', 'is_passing']), 'organization_id' => $organization->getKey(), 'display_order' => $band['display_order'] ?? $i]);
            }
            $this->audit->record($scale->wasRecentlyCreated ? 'reports.grading_scale_created' : 'reports.grading_scale_updated', $scale, after: ['organization_id' => $organization->getKey(), 'band_count' => count($bands)]);

            return $scale->refresh()->load('bands');
        }, 3);
    }

    public function setScaleState(GradingScale $scale, User $actor, bool $active, bool $default = false): GradingScale
    {
        return DB::transaction(function () use ($scale, $actor, $active, $default): GradingScale {
            if ($default && ! $active) {
                throw new DomainException('The default grading scale must be active.');
            }
            if ($default) {
                GradingScale::query()->where('organization_id', $scale->organization_id)->whereKeyNot($scale->id)->update(['is_default' => false]);
            }
            $scale->update(['is_active' => $active, 'is_default' => $default ?: ($active ? $scale->is_default : false), 'updated_by' => $actor->getKey()]);
            $this->audit->record($default ? 'reports.grading_scale_defaulted' : 'reports.grading_scale_state_changed', $scale, after: ['organization_id' => $scale->organization_id, 'active' => $active, 'default' => $default]);

            return $scale->refresh();
        }, 3);
    }

    public function savePeriod(Organization $organization, User $actor, array $data, ?ReportingPeriod $period = null): ReportingPeriod
    {
        if ($period && in_array($period->status, [ReportingPeriodStatus::Closed, ReportingPeriodStatus::Archived], true)) {
            throw new DomainException('Closed or archived reporting periods cannot be edited.');
        }
        $year = AcademicYear::query()->where('organization_id', $organization->getKey())->find($data['academic_year_id'] ?? null);
        $term = isset($data['academic_term_id']) ? AcademicTerm::query()->where('organization_id', $organization->getKey())->find($data['academic_term_id']) : null;
        if (! $year || ($term && $term->getAttribute('academic_year_id') !== $year->getKey())) {
            throw new DomainException('The year and term must belong to the active organization and match.');
        }
        if (($data['end_date'] ?? '') < ($data['start_date'] ?? '') || (($data['result_cutoff_date'] ?? null) && $data['result_cutoff_date'] < $data['start_date'])) {
            throw new DomainException('Reporting period dates are invalid.');
        }
        $overlap = ReportingPeriod::query()->where('organization_id', $organization->getKey())->where('academic_year_id', $year->getKey())->when($period, fn ($q) => $q->whereKeyNot($period->id))->whereNot('status', 'archived')->whereDate('start_date', '<=', $data['end_date'])->whereDate('end_date', '>=', $data['start_date'])->exists();
        if ($overlap) {
            throw new DomainException('The reporting period overlaps another non-archived period.');
        }
        $values = [...Arr::only($data, ['academic_year_id', 'academic_term_id', 'name', 'code', 'start_date', 'end_date', 'result_cutoff_date']), 'organization_id' => $organization->getKey(), 'updated_by' => $actor->getKey()];
        $period ? $period->update($values) : $period = ReportingPeriod::query()->create([...$values, 'status' => ReportingPeriodStatus::Draft, 'created_by' => $actor->getKey()]);
        $this->audit->record($period->wasRecentlyCreated ? 'reports.period_created' : 'reports.period_updated', $period, after: ['organization_id' => $organization->getKey(), 'status' => $period->status->value]);

        return $period->refresh();
    }

    public function transitionPeriod(ReportingPeriod $period, User $actor, ReportingPeriodStatus $status): ReportingPeriod
    {
        $allowed = match ($period->status) {
            ReportingPeriodStatus::Draft => [ReportingPeriodStatus::Open], ReportingPeriodStatus::Open => [ReportingPeriodStatus::Closed], ReportingPeriodStatus::Closed => [ReportingPeriodStatus::Archived], default => []
        };
        if (! in_array($status, $allowed, true)) {
            throw new DomainException('Invalid reporting period transition.');
        }
        $period->update(['status' => $status, 'updated_by' => $actor->getKey()]);
        $this->audit->record('reports.period_'.$status->value, $period, after: ['organization_id' => $period->organization_id]);

        return $period->refresh();
    }

    public function saveTemplate(Organization $organization, User $actor, array $data, ?ReportCardTemplate $template = null): ReportCardTemplate
    {
        if ($template && $template->organization_id !== $organization->getKey()) {
            throw new DomainException('The template must belong to the active organization.');
        }
        $values = [...Arr::only($data, ['name', 'description', 'is_active', 'show_attendance', 'show_assessment_breakdown', 'show_subject_comments', 'show_overall_comment', 'show_grading_legend', 'show_learner_photo', 'show_organization_logo', 'footer_text', 'page_size']), 'organization_id' => $organization->getKey(), 'updated_by' => $actor->getKey()];
        if (! in_array($values['page_size'] ?? 'A4', ['A4', 'LETTER'], true)) {
            throw new DomainException('Unsupported page size.');
        }
        $template ? $template->update($values) : $template = ReportCardTemplate::query()->create([...$values, 'created_by' => $actor->getKey()]);
        $this->audit->record($template->wasRecentlyCreated ? 'reports.template_created' : 'reports.template_updated', $template, after: ['organization_id' => $organization->getKey()]);

        return $template->refresh();
    }

    public function defaultTemplate(ReportCardTemplate $template, User $actor): ReportCardTemplate
    {
        return DB::transaction(function () use ($template, $actor): ReportCardTemplate {
            ReportCardTemplate::query()->where('organization_id', $template->organization_id)->update(['is_default' => false]);
            $template->update(['is_active' => true, 'is_default' => true, 'updated_by' => $actor->getKey()]);
            $this->audit->record('reports.template_defaulted', $template, after: ['organization_id' => $template->organization_id]);

            return $template->refresh();
        }, 3);
    }

    private function validateBands(array $bands): void
    {
        if ($bands === []) {
            throw new DomainException('A grading scale requires bands covering 0 through 100.');
        }
        usort($bands, fn ($a, $b) => (float) $a['minimum_percentage'] <=> (float) $b['minimum_percentage']);
        $expected = 0.0;
        foreach ($bands as $band) {
            $min = (float) ($band['minimum_percentage'] ?? -1);
            $max = (float) ($band['maximum_percentage'] ?? -1);
            if ($min < 0 || $max > 100 || $min > $max || abs($min - $expected) > 0.001) {
                throw new DomainException('Grading bands must be non-overlapping and continuously cover 0 through 100.');
            } $expected = $max + 0.01;
        }
        if (abs($expected - 100.01) > 0.001) {
            throw new DomainException('Grading bands must end at 100.');
        }
    }
}
