<?php

declare(strict_types=1);

namespace Modules\Assessments\Http\Controllers\Api\V1;

use Core\AuditLogs\Application\AuditLogService;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Assessments\Application\AssessmentResultService;
use Modules\Assessments\Application\AssessmentService;
use Modules\Assessments\Domain\Enums\AssessmentResultStatus;
use Modules\Assessments\Http\Requests\RecordAssessmentResultsRequest;
use Modules\Assessments\Http\Requests\StoreAssessmentRequest;
use Modules\Assessments\Http\Requests\UpdateAssessmentRequest;
use Modules\Assessments\Http\Resources\AssessmentResource;
use Modules\Assessments\Infrastructure\Models\Assessment;
use Modules\Assessments\Infrastructure\Models\AssessmentResult;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AssessmentController
{
    public function __construct(private readonly AssessmentService $service, private readonly AssessmentResultService $results, private readonly AuditLogService $audit) {}

    public function index(Request $request): mixed
    {
        Gate::authorize('viewAny', Assessment::class);

        return AssessmentResource::collection($this->query($request)->with(['academicYear', 'academicTerm', 'grade', 'classGroup', 'subject', 'category'])->withCount(['results', 'results as marked_count' => fn ($q) => $q->where('result_status', 'marked')])->paginate(min(100, max(1, $request->integer('per_page', 20)))));
    }

    public function store(StoreAssessmentRequest $request): AssessmentResource
    {
        return new AssessmentResource($this->service->create($this->organization($request), $this->actor($request), $request->validated())->load($this->relations()));
    }

    public function show(Request $request, Assessment $assessment): AssessmentResource
    {
        Gate::authorize('view', $assessment);

        return new AssessmentResource($assessment->load($this->relations()));
    }

    public function update(UpdateAssessmentRequest $request, Assessment $assessment): AssessmentResource
    {
        return new AssessmentResource($this->service->update($assessment, $this->actor($request), $request->validated())->load($this->relations()));
    }

    public function marks(RecordAssessmentResultsRequest $request, Assessment $assessment): AssessmentResource
    {
        return new AssessmentResource($this->results->record($assessment, $this->actor($request), $request->validated('results'))->load($this->relations()));
    }

    public function finalize(Request $request, Assessment $assessment): AssessmentResource
    {
        Gate::authorize('finalize', $assessment);

        return new AssessmentResource($this->service->finalize($assessment, $this->actor($request))->load($this->relations()));
    }

    public function reopen(Request $request, Assessment $assessment): AssessmentResource
    {
        Gate::authorize('reopen', $assessment);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return new AssessmentResource($this->service->reopen($assessment, $this->actor($request), $data['reason'])->load($this->relations()));
    }

    public function cancel(Request $request, Assessment $assessment): AssessmentResource
    {
        Gate::authorize('cancel', $assessment);

        return new AssessmentResource($this->service->cancel($assessment, $this->actor($request))->load($this->relations()));
    }

    public function release(Request $request, Assessment $assessment): AssessmentResource
    {
        Gate::authorize('release', $assessment);

        return new AssessmentResource($this->service->release($assessment, $this->actor($request), true)->load($this->relations()));
    }

    public function withhold(Request $request, Assessment $assessment): AssessmentResource
    {
        Gate::authorize('release', $assessment);

        return new AssessmentResource($this->service->release($assessment, $this->actor($request), false)->load($this->relations()));
    }

    public function learnerHistory(Request $request, string $learner): JsonResponse
    {
        Gate::authorize('viewAny', Assessment::class);
        $profile = LearnerProfile::query()->where('organization_id', $this->organization($request)->getKey())->where('uuid', $learner)->firstOrFail();
        $query = AssessmentResult::query()->where('organization_id', $profile->getAttribute('organization_id'))->where('learner_profile_id', $profile->getKey())->with(['assessment.category', 'assessment.subject']);
        foreach (['result_status'] as $f) {
            $query->when($request->query($f), fn ($q, $v) => $q->where($f, $v));
        }
        foreach (['academic_year_id', 'academic_term_id', 'subject_id', 'assessment_category_id'] as $f) {
            $query->when($request->query($f), fn ($q, $v) => $q->whereHas('assessment', fn ($a) => $a->where($f, $v)));
        }

        return response()->json(['data' => $query->latest()->paginate(20)]);
    }

    public function gradebook(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Assessment::class);

        return response()->json(['data' => $this->resultQuery($request)->paginate(25)]);
    }

    public function summary(Request $request): JsonResponse
    {
        Gate::authorize('viewReports', Assessment::class);
        $assessments = $this->query($request);
        $ids = (clone $assessments)->pluck('id');
        $results = AssessmentResult::query()->where('organization_id', $this->organization($request)->getKey())->whereIn('assessment_id', $ids);
        $marked = (clone $results)->where('result_status', AssessmentResultStatus::Marked->value);

        return response()->json(['assessment_count' => (clone $assessments)->count(), 'finalized_assessment_count' => (clone $assessments)->where('status', 'finalized')->count(), 'marked_result_count' => (clone $marked)->count(), 'result_status_totals' => (clone $results)->selectRaw('result_status, count(*) aggregate')->groupBy('result_status')->pluck('aggregate', 'result_status'), 'average_percentage' => $marked->avg('percentage'), 'highest_percentage' => (clone $marked)->max('percentage'), 'lowest_percentage' => (clone $marked)->min('percentage')]);
    }

    public function export(Request $request, Assessment $assessment): StreamedResponse
    {
        Gate::authorize('export', $assessment);
        $assessment->load(['results.learner']);
        $this->audit->record('assessment.mark_sheet_exported', $assessment, after: ['organization_id' => $assessment->organization_id, 'result_count' => $assessment->results->count(), 'status' => $assessment->status->value]);

        return response()->streamDownload(function () use ($assessment): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['Learner number', 'Learner name', 'Result status', 'Score', 'Maximum mark', 'Percentage', 'Feedback', 'Assessment status', 'Release state']);
            foreach ($assessment->results as $result) {
                $l = $result->learner;
                fputcsv($out, array_map($this->csv(...), [$l->getAttribute('learner_number'), trim($l->getAttribute('first_name').' '.$l->getAttribute('last_name')), $result->result_status->value, $result->score, $assessment->maximum_mark, $result->percentage, $result->feedback, $assessment->status->value, $assessment->result_release_status->value]));
            } fclose($out);
        }, 'assessment-'.$assessment->uuid.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function query(Request $request)
    {
        $q = Assessment::query()->where('organization_id', $this->organization($request)->getKey());
        foreach (['academic_year_id', 'academic_term_id', 'grade_id', 'class_id', 'subject_id', 'assessment_category_id', 'staff_profile_id', 'status', 'result_release_status'] as $f) {
            $q->when($request->query($f), fn ($x, $v) => $x->where($f, $v));
        } $q->when($request->query('date_from'), fn ($x, $v) => $x->whereDate('assessment_date', '>=', $v))->when($request->query('date_to'), fn ($x, $v) => $x->whereDate('assessment_date', '<=', $v))->when($request->query('search'), fn ($x, $v) => $x->where('title', 'like', '%'.$v.'%'));
        $sort = in_array($request->query('sort'), ['title', 'assessment_date', 'status', 'created_at'], true) ? $request->query('sort') : 'created_at';

        return $q->orderBy($sort, $request->query('direction') === 'asc' ? 'asc' : 'desc');
    }

    private function resultQuery(Request $request)
    {
        $q = AssessmentResult::query()->where('organization_id', $this->organization($request)->getKey())->with(['learner', 'assessment.subject']);
        foreach (['learner_profile_id', 'result_status'] as $f) {
            $q->when($request->query($f), fn ($x, $v) => $x->where($f, $v));
        } foreach (['academic_year_id', 'academic_term_id', 'grade_id', 'class_id', 'subject_id', 'assessment_category_id'] as $f) {
            $q->when($request->query($f), fn ($x, $v) => $x->whereHas('assessment', fn ($a) => $a->where($f, $v)));
        }

        return $q->latest();
    }

    private function relations(): array
    {
        return ['academicYear', 'academicTerm', 'grade', 'classGroup', 'subject', 'category', 'results.learner'];
    }

    private function csv(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function organization(Request $request): Organization
    {
        $organization = $request->attributes->get('organization');
        abort_unless($organization instanceof Organization, 403);

        return $organization;
    }
}
