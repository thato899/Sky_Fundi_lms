<?php

declare(strict_types=1);

namespace Modules\Reports\Http\Controllers\Api\V1;

use Barryvdh\DomPDF\Facade\Pdf;
use Core\AuditLogs\Application\AuditLogService;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Application\OrganizationService;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Reports\Application\ReportCardService;
use Modules\Reports\Application\ReportConfigurationService;
use Modules\Reports\Domain\Enums\ReportCardStatus;
use Modules\Reports\Domain\Enums\ReportingPeriodStatus;
use Modules\Reports\Http\Requests\GenerateReportCardRequest;
use Modules\Reports\Http\Requests\StoreReportCommentRequest;
use Modules\Reports\Infrastructure\Models\GradingScale;
use Modules\Reports\Infrastructure\Models\ReportCard;
use Modules\Reports\Infrastructure\Models\ReportCardTemplate;
use Modules\Reports\Infrastructure\Models\ReportingPeriod;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportController
{
    public function __construct(private readonly ReportConfigurationService $configuration, private readonly ReportCardService $reports, private readonly AuditLogService $audit, private readonly OrganizationService $organizations) {}

    public function scales(Request $r): JsonResponse
    {
        Gate::authorize('viewAny', GradingScale::class);

        return response()->json(['data' => GradingScale::query()->where('organization_id', $this->organization($r)->getKey())->with('bands')->paginate(20)]);
    }

    public function showScale(Request $r, GradingScale $scale): JsonResponse
    {
        Gate::authorize('view', $scale);

        return response()->json(['data' => $scale->load('bands')]);
    }

    public function saveScale(Request $r, ?GradingScale $scale = null): JsonResponse
    {
        Gate::authorize('manageGradingScales', $scale ?? GradingScale::class);
        $data = $r->validate($this->scaleRules());

        return response()->json(['data' => $this->configuration->saveScale($this->organization($r), $this->actor($r), $data, $scale)], $scale ? 200 : 201);
    }

    public function scaleState(Request $r, GradingScale $scale, string $action): JsonResponse
    {
        Gate::authorize('manageGradingScales', $scale);
        $active = $action !== 'deactivate';

        return response()->json(['data' => $this->configuration->setScaleState($scale, $this->actor($r), $active, $action === 'default')]);
    }

    public function periods(Request $r): JsonResponse
    {
        Gate::authorize('viewAny', ReportingPeriod::class);

        return response()->json(['data' => ReportingPeriod::query()->where('organization_id', $this->organization($r)->getKey())->with(['academicYear', 'academicTerm'])->paginate(20)]);
    }

    public function showPeriod(Request $r, ReportingPeriod $period): JsonResponse
    {
        Gate::authorize('view', $period);

        return response()->json(['data' => $period->load(['academicYear', 'academicTerm'])]);
    }

    public function savePeriod(Request $r, ?ReportingPeriod $period = null): JsonResponse
    {
        Gate::authorize('managePeriods', $period ?? ReportingPeriod::class);
        $data = $r->validate(['organization_id' => ['prohibited'], 'academic_year_id' => ['required', 'uuid'], 'academic_term_id' => ['nullable', 'uuid'], 'name' => ['required', 'string', 'max:255'], 'code' => ['nullable', 'string', 'max:64'], 'start_date' => ['required', 'date'], 'end_date' => ['required', 'date'], 'result_cutoff_date' => ['nullable', 'date']]);

        return response()->json(['data' => $this->configuration->savePeriod($this->organization($r), $this->actor($r), $data, $period)], $period ? 200 : 201);
    }

    public function periodState(Request $r, ReportingPeriod $period, string $action): JsonResponse
    {
        Gate::authorize('managePeriods', $period);
        $state = ReportingPeriodStatus::from($action === 'archive' ? 'archived' : ($action === 'close' ? 'closed' : 'open'));

        return response()->json(['data' => $this->configuration->transitionPeriod($period, $this->actor($r), $state)]);
    }

    public function templates(Request $r): JsonResponse
    {
        Gate::authorize('viewAny', ReportCardTemplate::class);

        return response()->json(['data' => ReportCardTemplate::query()->where('organization_id', $this->organization($r)->getKey())->paginate(20)]);
    }

    public function saveTemplate(Request $r, ?ReportCardTemplate $template = null): JsonResponse
    {
        Gate::authorize('manageTemplates', $template ?? ReportCardTemplate::class);
        $data = $r->validate($this->templateRules());

        return response()->json(['data' => $this->configuration->saveTemplate($this->organization($r), $this->actor($r), $data, $template)], $template ? 200 : 201);
    }

    public function defaultTemplate(Request $r, ReportCardTemplate $template): JsonResponse
    {
        Gate::authorize('manageTemplates', $template);

        return response()->json(['data' => $this->configuration->defaultTemplate($template, $this->actor($r))]);
    }

    public function index(Request $r): JsonResponse
    {
        Gate::authorize('viewAny', ReportCard::class);

        return response()->json(['data' => $this->cardQuery($r)->paginate(min(100, max(1, $r->integer('per_page', 20))))]);
    }

    public function show(Request $r, ReportCard $reportCard): JsonResponse
    {
        Gate::authorize('view', $reportCard);

        return response()->json(['data' => $reportCard->load($this->relations())->makeHidden('snapshot_metadata')]);
    }

    public function generate(GenerateReportCardRequest $r): JsonResponse
    {
        $o = $this->organization($r);
        [$learner, $period, $scale, $template] = $this->generationContext($r->validated(), $o);

        return response()->json(['data' => $this->reports->generate($learner, $period, $scale, $template, $this->actor($r))], 201);
    }

    public function regenerate(GenerateReportCardRequest $r, ReportCard $reportCard): JsonResponse
    {
        Gate::authorize('update', $reportCard);
        $o = $this->organization($r);
        [$learner, $period, $scale, $template] = $this->generationContext($r->validated(), $o);

        return response()->json(['data' => $this->reports->generate($learner, $period, $scale, $template, $this->actor($r), $reportCard)]);
    }

    public function comments(StoreReportCommentRequest $r, ReportCard $reportCard): JsonResponse
    {
        return response()->json(['data' => $this->reports->updateComments($reportCard, $this->actor($r), $r->validated())]);
    }

    public function lifecycle(Request $r, ReportCard $reportCard, string $action): JsonResponse
    {
        Gate::authorize($action, $reportCard);
        $reason = $action === 'withdraw' ? $r->validate(['reason' => ['required', 'string', 'max:2000']])['reason'] : null;
        $state = ReportCardStatus::from(match ($action) {
            'review' => 'under_review', 'approve' => 'approved', 'publish' => 'published', 'withdraw' => 'withdrawn', default => abort(404)
        });

        return response()->json(['data' => $this->reports->transition($reportCard, $this->actor($r), $state, $reason)]);
    }

    public function learnerHistory(Request $r, string $learner): JsonResponse
    {
        Gate::authorize('viewAny', ReportCard::class);
        $profile = LearnerProfile::query()->where('organization_id', $this->organization($r)->getKey())->where('uuid', $learner)->firstOrFail();

        return response()->json(['data' => ReportCard::query()->where('organization_id', $profile->getAttribute('organization_id'))->where('learner_profile_id', $profile->getKey())->with(['period', 'grade', 'classGroup'])->latest('version_number')->paginate(20)]);
    }

    public function pdf(Request $r, ReportCard $reportCard): Response
    {
        Gate::authorize('exportPdf', $reportCard);
        if ($reportCard->status !== ReportCardStatus::Published && ! Gate::allows('update', $reportCard)) {
            abort(403);
        } $reportCard->load($this->relations());
        $branding = $this->organizations->branding($this->organization($r));
        $this->audit->record('reports.pdf_exported', $reportCard, after: ['organization_id' => $reportCard->organization_id, 'version' => $reportCard->version_number]);

        return Pdf::loadView('reports.pdf', compact('reportCard', 'branding'))->setPaper(strtolower($reportCard->template->page_size))->download('report-card-'.$reportCard->learner->getAttribute('learner_number').'-'.$reportCard->period->code.'-v'.$reportCard->version_number.'.pdf');
    }

    public function export(Request $r): StreamedResponse
    {
        Gate::authorize('exportCsv', ReportCard::class);
        $cards = $this->cardQuery($r)->get();
        $this->audit->record('reports.csv_exported', after: ['organization_id' => $this->organization($r)->getKey(), 'report_count' => $cards->count()]);

        return response()->streamDownload(function () use ($cards): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['Learner number', 'Learner name', 'Grade', 'Class', 'Reporting period', 'Version', 'Status', 'Overall average', 'Published date']);
            foreach ($cards as $c) {
                $learner = $c->getRelation('learner');
                $grade = $c->getRelation('grade');
                $class = $c->getRelation('classGroup');
                $period = $c->getRelation('period');
                fputcsv($out, array_map($this->csv(...), [$learner->getAttribute('learner_number'), trim($learner->getAttribute('first_name').' '.$learner->getAttribute('last_name')), $grade->getAttribute('name'), $class->getAttribute('name'), $period->getAttribute('name'), $c->version_number, $c->status->value, $c->overall_average, $c->published_at?->toIso8601String()]));
            } fclose($out);
        }, 'report-cards.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function cardQuery(Request $r)
    {
        $q = ReportCard::query()->where('organization_id', $this->organization($r)->getKey())->with(['learner', 'period', 'grade', 'classGroup']);
        foreach (['academic_year_id', 'academic_term_id', 'reporting_period_id', 'grade_id', 'class_id', 'learner_profile_id', 'status'] as $f) {
            $q->when($r->query($f), fn ($x, $v) => $x->where($f, $v));
        } $sort = in_array($r->query('sort'), ['generated_at', 'published_at', 'status', 'overall_average'], true) ? $r->query('sort') : 'generated_at';

        return $q->orderBy($sort, $r->query('direction') === 'asc' ? 'asc' : 'desc');
    }

    private function generationContext(array $d, Organization $o): array
    {
        return [LearnerProfile::query()->where('organization_id', $o->getKey())->findOrFail($d['learner_id']), ReportingPeriod::query()->where('organization_id', $o->getKey())->findOrFail($d['reporting_period_id']), GradingScale::query()->where('organization_id', $o->getKey())->findOrFail($d['grading_scale_id']), ReportCardTemplate::query()->where('organization_id', $o->getKey())->findOrFail($d['report_card_template_id'])];
    }

    private function scaleRules(): array
    {
        return ['organization_id' => ['prohibited'], 'name' => ['required', 'string', 'max:255'], 'code' => ['nullable', 'string', 'max:64'], 'description' => ['nullable', 'string', 'max:2000'], 'pass_threshold' => ['nullable', 'numeric', 'between:0,100'], 'is_active' => ['boolean'], 'bands' => ['required', 'array', 'min:1'], 'bands.*.label' => ['required', 'string', 'max:255'], 'bands.*.minimum_percentage' => ['required', 'numeric', 'between:0,100'], 'bands.*.maximum_percentage' => ['required', 'numeric', 'between:0,100'], 'bands.*.symbol' => ['nullable', 'string', 'max:32'], 'bands.*.is_passing' => ['nullable', 'boolean']];
    }

    private function templateRules(): array
    {
        return ['organization_id' => ['prohibited'], 'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000'], 'is_active' => ['boolean'], 'show_attendance' => ['boolean'], 'show_assessment_breakdown' => ['boolean'], 'show_subject_comments' => ['boolean'], 'show_overall_comment' => ['boolean'], 'show_grading_legend' => ['boolean'], 'show_learner_photo' => ['boolean'], 'show_organization_logo' => ['boolean'], 'footer_text' => ['nullable', 'string', 'max:1000'], 'page_size' => ['required', Rule::in(['A4', 'LETTER'])]];
    }

    private function relations(): array
    {
        return ['learner', 'academicYear', 'academicTerm', 'period', 'template', 'gradingScale.bands', 'classGroup', 'grade', 'subjects', 'comments'];
    }

    private function csv(mixed $v): string
    {
        $v = (string) ($v ?? '');

        return preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v;
    }

    private function organization(Request $r): Organization
    {
        $o = $r->attributes->get('organization');
        abort_unless($o instanceof Organization, 403);

        return $o;
    }

    private function actor(Request $r): User
    {
        $u = $r->user();
        abort_unless($u instanceof User, 401);

        return $u;
    }
}
