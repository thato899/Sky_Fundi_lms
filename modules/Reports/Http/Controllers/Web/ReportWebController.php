<?php

declare(strict_types=1);

namespace Modules\Reports\Http\Controllers\Web;

use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Academics\Infrastructure\Models\AcademicTerm;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Application\OrganizationService;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Reports\Application\ReportCardService;
use Modules\Reports\Application\ReportConfigurationService;
use Modules\Reports\Domain\Enums\ReportCardStatus;
use Modules\Reports\Domain\Enums\ReportingPeriodStatus;
use Modules\Reports\Infrastructure\Models\GradingScale;
use Modules\Reports\Infrastructure\Models\ReportCard;
use Modules\Reports\Infrastructure\Models\ReportCardTemplate;
use Modules\Reports\Infrastructure\Models\ReportingPeriod;

final class ReportWebController
{
    public function __construct(private readonly ReportConfigurationService $configuration, private readonly ReportCardService $reports, private readonly PermissionResolver $permissions, private readonly OrganizationService $organizations) {}

    public function dashboard(Request $r): View
    {
        [$o] = $this->context($r, 'reports.view');
        $period = ReportingPeriod::query()->where('organization_id', $o->getKey())->where('status', 'open')->orderByDesc('start_date')->first();
        $counts = ReportCard::query()->where('organization_id', $o->getKey())->selectRaw('status, count(*) aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $eligible = LearnerProfile::query()->where('organization_id', $o->getKey())->whereIn('learner_status', ['admitted', 'active'])->when($period, fn ($q) => $q->where('current_academic_year_id', $period->academic_year_id))->count();
        $reported = $period ? ReportCard::query()->where('organization_id', $o->getKey())->where('reporting_period_id', $period->id)->distinct('learner_profile_id')->count('learner_profile_id') : 0;

        return view('reports.dashboard', $this->shared($r) + compact('period', 'counts', 'eligible', 'reported'));
    }

    public function scales(Request $r): View
    {
        [$o] = $this->context($r, 'reports.view');

        return view('reports.scales', $this->shared($r) + ['scales' => GradingScale::query()->where('organization_id', $o->getKey())->with('bands')->paginate(20)]);
    }

    public function saveScale(Request $r, ?GradingScale $scale = null): RedirectResponse
    {
        [$o] = $this->context($r, 'reports.manage_grading_scales');
        Gate::authorize('manageGradingScales', $scale ?? GradingScale::class);
        try {
            $data = $r->validate(['organization_id' => ['prohibited'], 'name' => ['required', 'string', 'max:255'], 'code' => ['nullable', 'string', 'max:64'], 'pass_threshold' => ['nullable', 'numeric', 'between:0,100'], 'is_active' => ['nullable', 'boolean'], 'bands' => ['required', 'array'], 'bands.*.label' => ['required'], 'bands.*.minimum_percentage' => ['required', 'numeric'], 'bands.*.maximum_percentage' => ['required', 'numeric'], 'bands.*.symbol' => ['nullable', 'string', 'max:32']]);
            $this->configuration->saveScale($o, $this->actor($r), $data, $scale);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['scale' => $e->getMessage()]);
        }

        return back()->with('status', 'Grading scale saved.');
    }

    public function scaleState(Request $r, GradingScale $scale, string $action): RedirectResponse
    {
        $this->context($r, 'reports.manage_grading_scales');
        Gate::authorize('manageGradingScales', $scale);
        $this->configuration->setScaleState($scale, $this->actor($r), $action !== 'deactivate', $action === 'default');

        return back()->with('status', 'Grading scale state updated.');
    }

    public function periods(Request $r): View
    {
        [$o] = $this->context($r, 'reports.view');

        return view('reports.periods', $this->shared($r) + ['periods' => ReportingPeriod::query()->where('organization_id', $o->getKey())->withCount('reportCards')->with(['academicYear', 'academicTerm'])->paginate(20), 'years' => AcademicYear::query()->where('organization_id', $o->getKey())->get(), 'terms' => AcademicTerm::query()->where('organization_id', $o->getKey())->get()]);
    }

    public function savePeriod(Request $r, ?ReportingPeriod $period = null): RedirectResponse
    {
        [$o] = $this->context($r, 'reports.manage_periods');
        Gate::authorize('managePeriods', $period ?? ReportingPeriod::class);
        try {
            $this->configuration->savePeriod($o, $this->actor($r), $r->validate(['organization_id' => ['prohibited'], 'academic_year_id' => ['required', 'uuid'], 'academic_term_id' => ['nullable', 'uuid'], 'name' => ['required', 'string', 'max:255'], 'code' => ['nullable', 'string', 'max:64'], 'start_date' => ['required', 'date'], 'end_date' => ['required', 'date'], 'result_cutoff_date' => ['nullable', 'date']]), $period);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['period' => $e->getMessage()]);
        }

        return back()->with('status', 'Reporting period saved.');
    }

    public function periodState(Request $r, ReportingPeriod $period, string $action): RedirectResponse
    {
        $this->context($r, 'reports.manage_periods');
        Gate::authorize('managePeriods', $period);
        try {
            $this->configuration->transitionPeriod($period, $this->actor($r), ReportingPeriodStatus::from(match ($action) {
                'open' => 'open', 'close' => 'closed', 'archive' => 'archived', default => throw new DomainException('Unknown period action.')
            }));
        } catch (DomainException $e) {
            return back()->withErrors(['period' => $e->getMessage()]);
        }

        return back()->with('status', 'Reporting period state updated.');
    }

    public function templates(Request $r): View
    {
        [$o] = $this->context($r, 'reports.view');

        return view('reports.templates', $this->shared($r) + ['templates' => ReportCardTemplate::query()->where('organization_id', $o->getKey())->paginate(20)]);
    }

    public function saveTemplate(Request $r, ?ReportCardTemplate $template = null): RedirectResponse
    {
        [$o] = $this->context($r, 'reports.manage_templates');
        Gate::authorize('manageTemplates', $template ?? ReportCardTemplate::class);
        $data = $r->validate(['organization_id' => ['prohibited'], 'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000'], 'page_size' => ['required', 'in:A4,LETTER'], 'footer_text' => ['nullable', 'string', 'max:1000'], 'is_active' => ['nullable', 'boolean'], 'show_attendance' => ['nullable', 'boolean'], 'show_assessment_breakdown' => ['nullable', 'boolean'], 'show_subject_comments' => ['nullable', 'boolean'], 'show_overall_comment' => ['nullable', 'boolean'], 'show_grading_legend' => ['nullable', 'boolean'], 'show_organization_logo' => ['nullable', 'boolean']]);
        $this->configuration->saveTemplate($o, $this->actor($r), $data, $template);

        return back()->with('status', 'Template saved.');
    }

    public function defaultTemplate(Request $r, ReportCardTemplate $template): RedirectResponse
    {
        $this->context($r, 'reports.manage_templates');
        Gate::authorize('manageTemplates', $template);
        $this->configuration->defaultTemplate($template, $this->actor($r));

        return back()->with('status', 'Default template updated.');
    }

    public function directory(Request $r): View
    {
        [$o] = $this->context($r, 'reports.view');
        $q = ReportCard::query()->where('organization_id', $o->getKey())->with(['learner', 'period', 'grade', 'classGroup']);
        foreach (['reporting_period_id', 'grade_id', 'class_id', 'learner_profile_id', 'status'] as $f) {
            $q->when($r->query($f), fn ($x, $v) => $x->where($f, $v));
        } $q->when($r->query('search'), fn ($x, $v) => $x->whereHas('learner', fn ($l) => $l->where('learner_number', 'like', '%'.$v.'%')->orWhere('first_name', 'like', '%'.$v.'%')->orWhere('last_name', 'like', '%'.$v.'%')));

        return view('reports.directory', $this->shared($r) + $this->options($o) + ['cards' => $q->latest('generated_at')->paginate(20)->withQueryString()]);
    }

    public function generateForm(Request $r): View
    {
        [$o] = $this->context($r, 'reports.generate');

        return view('reports.generate', $this->shared($r) + $this->options($o));
    }

    public function generate(Request $r): RedirectResponse
    {
        [$o] = $this->context($r, 'reports.generate');
        Gate::authorize('generate', ReportCard::class);
        $d = $r->validate(['learner_id' => ['nullable', 'uuid'], 'grade_id' => ['nullable', 'uuid'], 'class_id' => ['nullable', 'uuid'], 'reporting_period_id' => ['required', 'uuid'], 'grading_scale_id' => ['required', 'uuid'], 'report_card_template_id' => ['required', 'uuid']]);
        try {
            $period = ReportingPeriod::query()->where('organization_id', $o->getKey())->findOrFail($d['reporting_period_id']);
            $scale = GradingScale::query()->where('organization_id', $o->getKey())->findOrFail($d['grading_scale_id']);
            $template = ReportCardTemplate::query()->where('organization_id', $o->getKey())->findOrFail($d['report_card_template_id']);
            /** @var Collection<int, LearnerProfile> $learners */
            $learners = LearnerProfile::query()->where('organization_id', $o->getKey())->whereIn('learner_status', ['admitted', 'active'])->when($d['learner_id'] ?? null, fn ($q, $v) => $q->where('id', $v))->when($d['grade_id'] ?? null, fn ($q, $v) => $q->where('current_grade_id', $v))->when($d['class_id'] ?? null, fn ($q, $v) => $q->where('current_class_id', $v))->get();
            if ($learners->isEmpty()) {
                throw new DomainException('No eligible learners match the selected scope.');
            }
            $generated = [];
            $failed = [];
            foreach ($learners as $learner) {
                try {
                    $generated[] = $this->reports->generate($learner, $period, $scale, $template, $this->actor($r));
                } catch (\Throwable $e) {
                    $failed[] = $learner->getAttribute('learner_number').': '.$e->getMessage();
                }
            }
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['report' => $e->getMessage()]);
        }

        if (count($generated) === 1 && $failed === []) {
            return redirect()->route('reports.show', $generated[0]->uuid)->with('status', 'Report card generated.');
        }

        return redirect()->route('reports.index')->with('status', count($generated).' report cards generated; '.count($failed).' failed.'.($failed === [] ? '' : ' '.implode(' | ', $failed)));
    }

    public function regenerate(Request $r, ReportCard $reportCard): RedirectResponse
    {
        $this->context($r, 'reports.update');
        Gate::authorize('update', $reportCard);
        try {
            $this->reports->generate($reportCard->learner, $reportCard->period, $reportCard->gradingScale, $reportCard->template, $this->actor($r), $reportCard);
        } catch (DomainException $e) {
            return back()->withErrors(['report' => $e->getMessage()]);
        }

        return back()->with('status', 'Draft report regenerated from current eligible finalized data.');
    }

    public function show(Request $r, ReportCard $reportCard): View
    {
        $this->context($r, 'reports.view');
        Gate::authorize('view', $reportCard);
        $history = ReportCard::query()->where('organization_id', $reportCard->organization_id)->where('learner_profile_id', $reportCard->learner_profile_id)->where('reporting_period_id', $reportCard->reporting_period_id)->orderByDesc('version_number')->get();

        return view('reports.show', $this->shared($r) + ['reportCard' => $reportCard->load(['learner', 'period', 'template', 'gradingScale.bands', 'classGroup', 'grade', 'subjects', 'comments']), 'history' => $history]);
    }

    public function comments(Request $r, ReportCard $reportCard): RedirectResponse
    {
        $this->context($r, 'reports.manage_comments');
        Gate::authorize('manageComments', $reportCard);
        $data = $r->validate(['overall_comment' => ['nullable', 'string', 'max:4000'], 'comment_type' => ['nullable', 'in:subject,class_teacher,academic_admin,principal,general'], 'comment' => ['nullable', 'string', 'max:4000']]);
        if (in_array($data['comment_type'] ?? null, ['academic_admin', 'principal'], true)) {
            $this->context($r, 'reports.approve');
        }
        $this->reports->updateComments($reportCard, $this->actor($r), $data);

        return back()->with('status', 'Comments updated.');
    }

    public function lifecycle(Request $r, ReportCard $reportCard, string $action): RedirectResponse
    {
        $this->context($r, 'reports.'.$action);
        Gate::authorize($action, $reportCard);
        $reason = $action === 'withdraw' ? $r->validate(['reason' => ['required', 'string', 'max:2000']])['reason'] : null;
        $this->reports->transition($reportCard, $this->actor($r), ReportCardStatus::from(match ($action) {
            'review' => 'under_review', 'approve' => 'approved', 'publish' => 'published', 'withdraw' => 'withdrawn', default => abort(404)
        }), $reason);

        return back()->with('status', ucfirst($action).' completed.');
    }

    public function learnerHistory(Request $r, mixed $learner): View
    {
        [$o] = $this->context($r, 'reports.view');
        abort_unless($learner instanceof LearnerProfile && $learner->getAttribute('organization_id') === $o->getKey(), 404);

        return view('reports.history', $this->shared($r) + ['learner' => $learner, 'cards' => ReportCard::query()->where('organization_id', $o->getKey())->where('learner_profile_id', $learner->getKey())->with('period')->latest('generated_at')->paginate(20)]);
    }

    private function options(Organization $o): array
    {
        return ['periodOptions' => ReportingPeriod::query()->where('organization_id', $o->getKey())->orderByDesc('start_date')->get(), 'scaleOptions' => GradingScale::query()->where('organization_id', $o->getKey())->where('is_active', true)->get(), 'templateOptions' => ReportCardTemplate::query()->where('organization_id', $o->getKey())->where('is_active', true)->get(), 'learners' => LearnerProfile::query()->where('organization_id', $o->getKey())->whereIn('learner_status', ['admitted', 'active'])->orderBy('last_name')->get(), 'grades' => Grade::query()->where('organization_id', $o->getKey())->get(), 'classes' => ClassGroup::query()->where('organization_id', $o->getKey())->get()];
    }

    private function context(Request $r, string $permission): array
    {
        $o = $r->attributes->get('organization');
        $m = $r->attributes->get('organization_membership');
        abort_unless($o instanceof Organization && $m instanceof Membership && $this->permissions->allows($m, $permission), 403);

        return [$o, $m];
    }

    private function shared(Request $r): array
    {
        $o = $r->attributes->get('organization');
        $m = $r->attributes->get('organization_membership');
        abort_unless($o instanceof Organization && $m instanceof Membership, 403);

        return ['organization' => $o, 'branding' => $this->organizations->branding($o), 'permissions' => $this->permissions->permissions($m)];
    }

    private function actor(Request $r): User
    {
        $u = $r->user();
        abort_unless($u instanceof User, 401);

        return $u;
    }
}
