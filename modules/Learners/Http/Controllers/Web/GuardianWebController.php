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
use Modules\Assessments\Infrastructure\Models\QuizAttempt;
use Modules\Learners\Application\GuardianPortalAccessService;
use Modules\Learners\Application\GuardianService;
use Modules\Learners\Http\Requests\StoreGuardianRelationshipRequest;
use Modules\Learners\Http\Requests\StoreGuardianRequest;
use Modules\Learners\Http\Requests\StoreLearnerConsentRequest;
use Modules\Learners\Http\Requests\UpdateGuardianRelationshipRequest;
use Modules\Learners\Http\Requests\UpdateGuardianRequest;
use Modules\Learners\Infrastructure\Models\GuardianProfile;
use Modules\Learners\Infrastructure\Models\LearnerGuardianRelationship;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Application\OrganizationService;
use Modules\Organizations\Infrastructure\Models\Organization;

final class GuardianWebController
{
    public function __construct(private readonly GuardianService $guardians, private readonly GuardianPortalAccessService $portalAccess, private readonly PermissionResolver $permissions, private readonly OrganizationService $organizations) {}

    public function index(Request $request): View
    {
        [$organization, $membership] = $this->context($request);
        Gate::authorize('viewAny', GuardianProfile::class);
        $search = trim((string) $request->query('search'));
        $guardians = GuardianProfile::query()->where('organization_id', $organization->getKey())->whereNull('archived_at')
            ->when($search !== '', fn ($q) => $q->where(fn ($nested) => $nested->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('last_name')->orderBy('first_name')->paginate(25)->withQueryString();

        return view('guardians.index', $this->shared($organization, $membership) + compact('guardians'));
    }

    public function create(Request $request): View
    {
        [$organization, $membership] = $this->context($request);
        Gate::authorize('create', GuardianProfile::class);

        return view('guardians.form', $this->shared($organization, $membership) + ['guardian' => null]);
    }

    public function store(StoreGuardianRequest $request): RedirectResponse
    {
        [$organization] = $this->context($request);
        try {
            $guardian = $this->guardians->create($organization, $this->actor($request), $request->validated());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['guardian' => $exception->getMessage()]);
        }

        return redirect()->route('guardians.show', $guardian->getAttribute('uuid'))->with('status', 'Guardian created.');
    }

    public function show(Request $request, mixed $guardian): View
    {
        $guardian = $this->guardian($guardian);
        [$organization, $membership] = $this->context($request);
        Gate::authorize('view', $guardian);
        $canManageProfile = Gate::allows('update', $guardian)
            || Gate::allows('manageRelationships', $guardian)
            || Gate::allows('viewInvitations', $guardian)
            || Gate::allows('invite', $guardian);
        if ($canManageProfile) {
            $guardian->load(['organizationMembership', 'relationships' => fn ($query) => $query->with('learner')->where('status', 'active')]);

            return view('guardians.show', $this->shared($organization, $membership) + compact('guardian'));
        }

        $guardian->load(['relationships' => fn ($query) => $query
            ->with('learner')
            ->where('status', 'active')
            ->whereRaw('(effective_from is null or effective_from <= ?)', [today()->toDateString()])
            ->whereRaw('(effective_until is null or effective_until >= ?)', [today()->toDateString()])
            ->whereHas('learner', fn ($learnerQuery) => $learnerQuery
                ->whereNull('archived_at')
                ->whereNull('deleted_at')
                ->whereIn('learner_status', ['admitted', 'active', 'temporarily_inactive', 'suspended']))]);

        $academicSummaries = [];
        foreach ($guardian->relationships as $relationship) {
            if (! $relationship->getAttribute('receives_academic_communication') || ! $this->portalAccess->allows($this->actor($request), $relationship->learner)) {
                continue;
            }
            $academicSummaries[$relationship->learner->getKey()] = QuizAttempt::query()
                ->where('organization_id', $organization->getKey())
                ->where('learner_profile_id', $relationship->learner->getKey())
                ->where('status', 'released')
                ->with(['assessment.subject', 'result', 'answers.question', 'publishedStudyPlan'])
                ->latest('released_at')
                ->first();
        }

        return view('guardians.portal-show', $this->shared($organization, $membership) + compact('guardian', 'academicSummaries'));
    }

    public function edit(Request $request, mixed $guardian): View
    {
        $guardian = $this->guardian($guardian);
        [$organization, $membership] = $this->context($request);
        Gate::authorize('update', $guardian);

        return view('guardians.form', $this->shared($organization, $membership) + compact('guardian'));
    }

    public function update(UpdateGuardianRequest $request, mixed $guardian): RedirectResponse
    {
        $guardian = $this->guardian($guardian);
        $this->context($request);
        $this->guardians->update($guardian, $this->actor($request), $request->validated());

        return redirect()->route('guardians.show', $guardian->getAttribute('uuid'))->with('status', 'Guardian updated.');
    }

    public function archive(Request $request, mixed $guardian): RedirectResponse
    {
        $guardian = $this->guardian($guardian);
        Gate::authorize('archive', $guardian);
        $this->guardians->archive($guardian, $this->actor($request));

        return redirect()->route('guardians.index')->with('status', 'Guardian archived.');
    }

    public function link(StoreGuardianRelationshipRequest $request, mixed $learner): RedirectResponse
    {
        $learner = $this->learner($learner);
        $this->context($request);
        /** @var GuardianProfile $guardian */
        $guardian = GuardianProfile::query()->where('organization_id', $learner->getAttribute('organization_id'))->where('uuid', $request->validated('guardian_uuid'))->whereNull('archived_at')->firstOrFail();
        try {
            $this->guardians->link($learner, $guardian, $this->actor($request), $request->validated());
        } catch (DomainException $exception) {
            return back()->withErrors(['guardian_relationship' => $exception->getMessage()]);
        }

        return back()->with('status', 'Guardian linked.');
    }

    public function updateRelationship(UpdateGuardianRelationshipRequest $request, mixed $learner, string $relationship): RedirectResponse
    {
        $this->guardians->updateRelationship($this->relationship($this->learner($learner), $relationship), $this->actor($request), $request->validated());

        return back()->with('status', 'Guardian relationship updated.');
    }

    public function unlink(Request $request, mixed $learner, string $relationship): RedirectResponse
    {
        $learner = $this->learner($learner);
        Gate::authorize('manageGuardians', $learner);
        $this->guardians->unlink($this->relationship($learner, $relationship), $this->actor($request));

        return back()->with('status', 'Guardian relationship removed.');
    }

    public function recordConsent(StoreLearnerConsentRequest $request, mixed $learner): RedirectResponse
    {
        $learner = $this->learner($learner);
        $guardian = $request->validated('guardian_uuid')
            ? GuardianProfile::query()->where('organization_id', $learner->getAttribute('organization_id'))->where('uuid', $request->validated('guardian_uuid'))->firstOrFail()
            : null;
        try {
            $this->guardians->recordConsent($learner, $guardian, $this->actor($request), $request->validated());
        } catch (DomainException $exception) {
            return back()->withErrors(['consent' => $exception->getMessage()]);
        }

        return back()->with('status', 'Consent recorded.');
    }

    private function relationship(LearnerProfile $learner, string $uuid): LearnerGuardianRelationship
    {
        return LearnerGuardianRelationship::query()->where('organization_id', $learner->getAttribute('organization_id'))->where('learner_profile_id', $learner->getKey())->where('uuid', $uuid)->firstOrFail();
    }

    private function guardian(mixed $guardian): GuardianProfile
    {
        abort_unless($guardian instanceof GuardianProfile, 404);

        return $guardian;
    }

    private function learner(mixed $learner): LearnerProfile
    {
        abort_unless($learner instanceof LearnerProfile, 404);

        return $learner;
    }

    private function context(Request $request): array
    {
        $organization = $request->attributes->get('organization');
        $membership = $request->attributes->get('organization_membership');
        abort_unless($organization instanceof Organization && $membership instanceof Membership, 403);

        return [$organization, $membership];
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private function shared(Organization $organization, Membership $membership): array
    {
        return ['branding' => $this->organizations->branding($organization), 'organization' => $organization, 'permissions' => $this->permissions->permissions($membership)];
    }
}
