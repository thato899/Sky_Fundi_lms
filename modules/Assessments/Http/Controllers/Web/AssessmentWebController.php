<?php

declare(strict_types=1);

namespace Modules\Assessments\Http\Controllers\Web;

use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Academics\Infrastructure\Models\AcademicTerm;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Assessments\Application\AssessmentCategoryService;
use Modules\Assessments\Application\AssessmentResultService;
use Modules\Assessments\Application\AssessmentService;
use Modules\Assessments\Domain\Enums\AssessmentResultStatus;
use Modules\Assessments\Http\Requests\RecordAssessmentResultsRequest;
use Modules\Assessments\Http\Requests\StoreAssessmentRequest;
use Modules\Assessments\Infrastructure\Models\Assessment;
use Modules\Assessments\Infrastructure\Models\AssessmentCategory;
use Modules\Assessments\Infrastructure\Models\AssessmentResult;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Application\OrganizationService;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Staff\Infrastructure\Models\StaffProfile;

final class AssessmentWebController
{
    public function __construct(private readonly AssessmentService $service, private readonly AssessmentResultService $results, private readonly AssessmentCategoryService $categories, private readonly PermissionResolver $permissions, private readonly OrganizationService $organizations) {}

    public function index(Request $request): View
    {
        [$o] = $this->context($request, 'assessments.view');
        $query = Assessment::query()->where('organization_id', $o->id)->with(['academicYear', 'academicTerm', 'grade', 'classGroup', 'subject', 'category'])->withCount(['results', 'results as marked_count' => fn ($q) => $q->where('result_status', 'marked')]);
        foreach (['academic_year_id', 'academic_term_id', 'grade_id', 'class_id', 'subject_id', 'assessment_category_id', 'staff_profile_id', 'status', 'result_release_status'] as $f) {
            $query->when($request->query($f), fn ($q, $v) => $q->where($f, $v));
        } $query->when($request->query('search'), fn ($q, $v) => $q->where('title', 'like', '%'.$v.'%'));
        $sort = in_array($request->query('sort'), ['title', 'assessment_date', 'status', 'created_at'], true) ? $request->query('sort') : 'created_at';
        $assessments = $query->orderBy($sort, $request->query('direction') === 'asc' ? 'asc' : 'desc')->paginate(20)->withQueryString();
        $counts = Assessment::query()->where('organization_id', $o->id)->selectRaw('status, count(*) aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $releaseCounts = Assessment::query()->where('organization_id', $o->id)->selectRaw('result_release_status, count(*) aggregate')->groupBy('result_release_status')->pluck('aggregate', 'result_release_status');
        $pending = AssessmentResult::query()->where('organization_id', $o->id)->where('result_status', 'pending')->count();

        return view('assessments.index', $this->shared($request) + $this->options($o) + compact('assessments', 'counts', 'releaseCounts', 'pending'));
    }

    public function create(Request $r): View
    {
        [$o] = $this->context($r, 'assessments.create');
        Gate::authorize('create', Assessment::class);

        return view('assessments.form', $this->shared($r) + $this->options($o));
    }

    public function store(StoreAssessmentRequest $r): RedirectResponse
    {
        [$o] = $this->context($r, 'assessments.create');
        try {
            $a = $this->service->create($o, $this->actor($r), $r->validated());
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['assessment' => $e->getMessage()]);
        }

        return redirect()->route('assessments.show', $a->getAttribute('uuid'))->with('status', 'Assessment created and eligible learners populated.');
    }

    public function show(Request $r, Assessment $assessment): View
    {
        $this->context($r, 'assessments.view');
        Gate::authorize('view', $assessment);

        return view('assessments.mark-sheet', $this->shared($r) + ['assessment' => $assessment->load(['academicYear', 'academicTerm', 'grade', 'classGroup', 'subject', 'category', 'results.learner.currentClass']), 'statuses' => AssessmentResultStatus::cases()]);
    }

    public function marks(RecordAssessmentResultsRequest $r, Assessment $assessment): RedirectResponse
    {
        try {
            $this->results->record($assessment, $this->actor($r), $r->validated('results'));
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['marks' => $e->getMessage()]);
        }

        return back()->with('status', 'Complete mark sheet saved atomically.');
    }

    public function action(Request $r, Assessment $assessment, string $action): RedirectResponse
    {
        $permission = $action === 'withhold' ? 'release' : $action;
        $this->context($r, 'assessments.'.$permission);
        Gate::authorize($permission, $assessment);
        try {
            match ($action) {
                'finalize' => $this->service->finalize($assessment, $this->actor($r)), 'reopen' => $this->service->reopen($assessment, $this->actor($r), (string) $r->validate(['reason' => ['required', 'string', 'max:1000']])['reason']), 'cancel' => $this->service->cancel($assessment, $this->actor($r)), 'release' => $this->service->release($assessment, $this->actor($r), true), 'withhold' => $this->service->release($assessment, $this->actor($r), false), default => abort(404)
            };
        } catch (DomainException $e) {
            return back()->withErrors([$action => $e->getMessage()]);
        }

        return back()->with('status', ucfirst($action).' completed.');
    }

    public function categories(Request $r): View
    {
        [$o] = $this->context($r, 'assessment_categories.manage');
        Gate::authorize('viewAny', AssessmentCategory::class);

        return view('assessments.categories', $this->shared($r) + ['categories' => AssessmentCategory::query()->where('organization_id', $o->id)->orderBy('display_order')->paginate(50)]);
    }

    public function storeCategory(Request $r): RedirectResponse
    {
        [$o] = $this->context($r, 'assessment_categories.manage');
        Gate::authorize('manageCategories', AssessmentCategory::class);
        $this->categories->create($o, $this->actor($r), $this->categoryData($r));

        return back()->with('status', 'Category created.');
    }

    public function updateCategory(Request $r, AssessmentCategory $category): RedirectResponse
    {
        Gate::authorize('manageCategories', $category);
        $this->categories->update($category, $this->actor($r), $this->categoryData($r));

        return back()->with('status', 'Category updated.');
    }

    public function toggleCategory(Request $r, AssessmentCategory $category, bool $active): RedirectResponse
    {
        Gate::authorize('manageCategories', $category);
        $this->categories->active($category, $this->actor($r), $active);

        return back()->with('status', 'Category state updated.');
    }

    public function gradebook(Request $r): View
    {
        [$o] = $this->context($r, 'assessments.view');
        $q = AssessmentResult::query()->where('organization_id', $o->id)->with(['learner', 'assessment.subject']);
        foreach (['learner_profile_id', 'result_status'] as $f) {
            $q->when($r->query($f), fn ($x, $v) => $x->where($f, $v));
        } foreach (['academic_year_id', 'academic_term_id', 'grade_id', 'class_id', 'subject_id', 'assessment_category_id'] as $f) {
            $q->when($r->query($f), fn ($x, $v) => $x->whereHas('assessment', fn ($a) => $a->where($f, $v)));
        }

        return view('assessments.gradebook', $this->shared($r) + $this->options($o) + ['results' => $q->latest()->paginate(25)->withQueryString()]);
    }

    public function learnerHistory(Request $r, mixed $learner): View
    {
        [$o] = $this->context($r, 'assessments.view');
        abort_unless($learner instanceof LearnerProfile && $learner->getAttribute('organization_id') === $o->getKey(), 404);
        $q = AssessmentResult::query()->where('organization_id', $o->id)->where('learner_profile_id', $learner->id)->with(['assessment.category', 'assessment.subject']);
        foreach (['result_status'] as $f) {
            $q->when($r->query($f), fn ($x, $v) => $x->where($f, $v));
        }

        return view('assessments.history', $this->shared($r) + ['learner' => $learner, 'results' => $q->latest()->paginate(20)]);
    }

    public function reports(Request $r): View
    {
        [$o] = $this->context($r, 'assessments.view_reports');
        $a = Assessment::query()->where('organization_id', $o->id);
        $ids = (clone $a)->pluck('id');
        $results = AssessmentResult::query()->whereIn('assessment_id', $ids);
        $marked = (clone $results)->where('result_status', 'marked');

        return view('assessments.reports', $this->shared($r) + ['assessmentCount' => $a->count(), 'finalizedCount' => (clone $a)->where('status', 'finalized')->count(), 'markedCount' => $marked->count(), 'average' => (clone $marked)->avg('percentage'), 'highest' => (clone $marked)->max('percentage'), 'lowest' => (clone $marked)->min('percentage'), 'totals' => $results->selectRaw('result_status, count(*) aggregate')->groupBy('result_status')->pluck('aggregate', 'result_status')]);
    }

    private function categoryData(Request $r): array
    {
        return $r->validate(['organization_id' => ['prohibited'], 'name' => ['required', 'string', 'max:255'], 'code' => ['nullable', 'string', 'max:64'], 'description' => ['nullable', 'string', 'max:2000'], 'default_weighting' => ['nullable', 'numeric', 'between:0,100'], 'display_order' => ['nullable', 'integer', 'min:0']]);
    }

    private function options(Organization $o): array
    {
        $id = $o->getKey();

        return ['years' => AcademicYear::query()->where('organization_id', $id)->orderByDesc('start_date')->get(), 'terms' => AcademicTerm::query()->where('organization_id', $id)->orderBy('start_date')->get(), 'grades' => Grade::query()->where('organization_id', $id)->orderBy('order')->get(), 'classes' => ClassGroup::query()->where('organization_id', $id)->orderBy('name')->get(), 'subjects' => Subject::query()->where('organization_id', $id)->orderBy('name')->get(), 'categoryOptions' => AssessmentCategory::query()->where('organization_id', $id)->where('is_active', true)->orderBy('display_order')->get(), 'staff' => StaffProfile::query()->where('organization_id', $id)->where('employment_status', 'active')->orderBy('last_name')->get(), 'learners' => LearnerProfile::query()->where('organization_id', $id)->whereIn('learner_status', ['admitted', 'active'])->orderBy('last_name')->get()];
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
