<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Controllers\Web;

use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Academics\Domain\Enums\AcademicStatus;
use Modules\Academics\Domain\Enums\AcademicYearStatus;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Curriculum;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Learners\Application\LearnerDirectoryService;
use Modules\Learners\Application\LearnerService;
use Modules\Learners\Application\LearnerStatusService;
use Modules\Learners\Domain\Enums\LearnerStatus;
use Modules\Learners\Http\Requests\ArchiveLearnerRequest;
use Modules\Learners\Http\Requests\LearnerIndexRequest;
use Modules\Learners\Http\Requests\RestoreLearnerRequest;
use Modules\Learners\Http\Requests\StoreLearnerRequest;
use Modules\Learners\Http\Requests\TransitionLearnerStatusRequest;
use Modules\Learners\Http\Requests\UpdateAcademicPlacementRequest;
use Modules\Learners\Http\Requests\UpdateLearnerRequest;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Application\OrganizationService;
use Modules\Organizations\Infrastructure\Models\Organization;

final class LearnerWebController
{
    public function __construct(
        private readonly LearnerService $learners,
        private readonly LearnerDirectoryService $directory,
        private readonly LearnerStatusService $statuses,
        private readonly PermissionResolver $permissions,
        private readonly OrganizationService $organizations,
    ) {}

    public function index(LearnerIndexRequest $request): View
    {
        [$organization, $membership] = $this->context($request);
        $learners = $this->directory->paginate($organization, $request->validated())->withQueryString();

        return view('learners.index', $this->shared($organization, $membership) + $this->academicOptions($organization) + [
            'learners' => $learners,
            'filtersApplied' => collect($request->validated())->except(['page', 'per_page'])->filter(fn (mixed $value): bool => $value !== null && $value !== '')->isNotEmpty(),
            'hasAnyLearners' => LearnerProfile::query()->where('organization_id', $organization->getKey())->exists(),
        ]);
    }

    public function create(Request $request): View
    {
        [$organization, $membership] = $this->context($request);
        Gate::authorize('create', LearnerProfile::class);

        return view('learners.form', $this->shared($organization, $membership) + $this->academicOptions($organization) + ['learner' => null]);
    }

    public function store(StoreLearnerRequest $request): RedirectResponse
    {
        [$organization] = $this->context($request);
        $actor = $this->actor($request);
        $manualAllowed = ! $request->filled('learner_number') || $actor->can('overrideNumber', LearnerProfile::class);

        try {
            $learner = $this->learners->create($organization, $actor, $request->validated(), $manualAllowed);
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['learner' => $exception->getMessage()]);
        }

        return redirect()->route('learners.show', $learner->getAttribute('uuid'))->with('status', 'Learner profile created.');
    }

    public function show(Request $request, mixed $learner): View
    {
        $learner = $this->learner($learner);
        [$organization, $membership] = $this->context($request);
        Gate::authorize('view', $learner);
        $learner->load(['currentAcademicYear', 'currentGrade', 'currentClass', 'curriculum']);
        $history = Gate::allows('viewStatusHistory', $learner)
            ? $learner->statusHistory()->with('actor:id,name')->orderByDesc('changed_at')->orderByDesc('id')->get()
            : collect();

        return view('learners.show', $this->shared($organization, $membership) + [
            'learner' => $learner,
            'history' => $history,
            'transitions' => Gate::allows('manageStatus', $learner) ? $this->statuses->availableTransitions($learner) : [],
        ]);
    }

    public function edit(Request $request, mixed $learner): View
    {
        $learner = $this->learner($learner);
        [$organization, $membership] = $this->context($request);
        Gate::authorize('update', $learner);

        return view('learners.form', $this->shared($organization, $membership) + $this->academicOptions($organization) + ['learner' => $learner]);
    }

    public function update(UpdateLearnerRequest $request, mixed $learner): RedirectResponse
    {
        $learner = $this->learner($learner);
        $this->context($request);
        $learner = $this->learners->update($learner, $this->actor($request), $request->validated());

        return redirect()->route('learners.show', $learner->getAttribute('uuid'))->with('status', 'Learner profile updated.');
    }

    public function editAcademicPlacement(Request $request, mixed $learner): View
    {
        $learner = $this->learner($learner);
        [$organization, $membership] = $this->context($request);
        Gate::authorize('manageAcademicProfile', $learner);

        return view('learners.academic-placement', $this->shared($organization, $membership) + $this->academicOptions($organization) + ['learner' => $learner]);
    }

    public function updateAcademicPlacement(UpdateAcademicPlacementRequest $request, mixed $learner): RedirectResponse
    {
        $learner = $this->learner($learner);
        $this->context($request);
        try {
            $this->learners->updateAcademicPlacement($learner, $this->actor($request), $request->validated());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['academic_placement' => $exception->getMessage()]);
        }

        return redirect()->route('learners.show', $learner->getAttribute('uuid'))->with('status', 'Academic placement updated.');
    }

    public function status(TransitionLearnerStatusRequest $request, mixed $learner): RedirectResponse
    {
        $learner = $this->learner($learner);

        return $this->statusAction($request, $learner, fn () => $this->learners->transition($learner, $this->actor($request), LearnerStatus::from($request->validated('status')), $request->validated('reason')), 'Learner status updated.');
    }

    public function archive(ArchiveLearnerRequest $request, mixed $learner): RedirectResponse
    {
        $learner = $this->learner($learner);

        return $this->statusAction($request, $learner, fn () => $this->learners->archive($learner, $this->actor($request), $request->validated('reason')), 'Learner archived.');
    }

    public function restore(RestoreLearnerRequest $request, mixed $learner): RedirectResponse
    {
        $learner = $this->learner($learner);

        return $this->statusAction($request, $learner, fn () => $this->learners->restore($learner, $this->actor($request), $request->validated('reason')), 'Learner restored.');
    }

    private function statusAction(Request $request, LearnerProfile $learner, callable $action, string $message): RedirectResponse
    {
        $this->context($request);
        try {
            $action();
        } catch (DomainException $exception) {
            return redirect()->route('learners.show', $learner->getAttribute('uuid'))->withErrors(['status_action' => $exception->getMessage()]);
        }

        return redirect()->route('learners.show', $learner->getAttribute('uuid'))->with('status', $message);
    }

    /** @return array{Organization, Membership} */
    private function context(Request $request): array
    {
        $organization = $request->attributes->get('organization');
        $membership = $request->attributes->get('organization_membership');
        abort_unless($organization instanceof Organization && $membership instanceof Membership, 403);

        return [$organization, $membership];
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function learner(mixed $learner): LearnerProfile
    {
        abort_unless($learner instanceof LearnerProfile, 404);

        return $learner;
    }

    private function shared(Organization $organization, Membership $membership): array
    {
        return [
            'branding' => $this->organizations->branding($organization),
            'organization' => $organization,
            'permissions' => $this->permissions->permissions($membership),
        ];
    }

    private function academicOptions(Organization $organization): array
    {
        $organizationId = $organization->getKey();

        return [
            'academicYears' => AcademicYear::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->where('status', '!=', AcademicYearStatus::Archived->value)->orderByDesc('start_date')->get(['id', 'name']),
            'curricula' => Curriculum::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'grades' => Grade::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->where('status', AcademicStatus::Active->value)->orderBy('name')->get(['id', 'name', 'academic_year_id', 'curriculum_id']),
            'classes' => ClassGroup::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->where('status', AcademicStatus::Active->value)->orderBy('name')->get(['id', 'name', 'grade_id', 'academic_year_id']),
        ];
    }
}
