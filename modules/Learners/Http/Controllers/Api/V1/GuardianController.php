<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Learners\Application\GuardianPortalAccessService;
use Modules\Learners\Application\GuardianService;
use Modules\Learners\Http\Requests\StoreGuardianRelationshipRequest;
use Modules\Learners\Http\Requests\StoreGuardianRequest;
use Modules\Learners\Http\Requests\StoreLearnerConsentRequest;
use Modules\Learners\Http\Requests\UpdateGuardianRelationshipRequest;
use Modules\Learners\Http\Requests\UpdateGuardianRequest;
use Modules\Learners\Http\Resources\GuardianRelationshipResource;
use Modules\Learners\Http\Resources\GuardianResource;
use Modules\Learners\Infrastructure\Models\GuardianProfile;
use Modules\Learners\Infrastructure\Models\LearnerGuardianRelationship;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;

final class GuardianController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly GuardianService $guardians,
        private readonly GuardianPortalAccessService $guardianAccess,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', GuardianProfile::class);
        $validated = validator($request->query(), ['search' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'in:active,inactive,archived'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']])->validate();
        $archived = ($validated['status'] ?? null) === 'archived';
        if ($archived) {
            Gate::authorize('viewArchived', GuardianProfile::class);
        }
        $query = GuardianProfile::query()->with('organizationMembership')->where('organization_id', $this->organization($request)->getKey())
            ->when($archived, fn ($query) => $query->whereNotNull('archived_at'), fn ($query) => $query->whereNull('archived_at'))
            ->when($validated['search'] ?? null, fn ($q, $search) => $q->where(fn ($nested) => $nested->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderBy('last_name')->orderBy('first_name');

        return $this->ok(GuardianResource::collection($query->paginate((int) ($validated['per_page'] ?? 25))));
    }

    public function store(StoreGuardianRequest $request): JsonResponse
    {
        return $this->created(new GuardianResource($this->guardians->create($this->organization($request), $this->actor($request), $request->validated())));
    }

    public function show(Request $request): JsonResponse
    {
        $guardian = $this->guardian($request);
        Gate::authorize('view', $guardian);

        return $this->ok(new GuardianResource($guardian->load('organizationMembership')));
    }

    public function update(UpdateGuardianRequest $request): JsonResponse
    {
        return $this->ok(new GuardianResource($this->guardians->update($this->guardian($request), $this->actor($request), $request->validated())->load('organizationMembership')));
    }

    public function archive(Request $request): JsonResponse
    {
        $guardian = $this->guardian($request);
        Gate::authorize('archive', $guardian);

        return $this->ok(new GuardianResource($this->guardians->archive($guardian, $this->actor($request))));
    }

    public function learnerGuardians(Request $request): JsonResponse
    {
        $learner = $this->learner($request);
        Gate::authorize('view', $learner);

        $query = $learner->guardianRelationships()->with('guardian.organizationMembership')->where('status', 'active');
        if (! Gate::allows('manageGuardians', $learner)) {
            $actor = $this->actor($request);
            $query = $this->guardianAccess->relationships($actor, $learner)->with('guardian.organizationMembership');
        }

        return $this->ok(GuardianRelationshipResource::collection($query->get()));
    }

    public function link(StoreGuardianRelationshipRequest $request): JsonResponse
    {
        $learner = $this->learner($request);
        /** @var GuardianProfile $guardian */
        $guardian = GuardianProfile::query()->where('organization_id', $learner->getAttribute('organization_id'))->where('uuid', $request->validated('guardian_uuid'))->whereNull('archived_at')->firstOrFail();

        return $this->created(new GuardianRelationshipResource($this->guardians->link($learner, $guardian, $this->actor($request), $request->validated())));
    }

    public function updateRelationship(UpdateGuardianRelationshipRequest $request): JsonResponse
    {
        return $this->ok(new GuardianRelationshipResource($this->guardians->updateRelationship($this->relationship($request), $this->actor($request), $request->validated())));
    }

    public function unlink(Request $request): JsonResponse
    {
        $learner = $this->learner($request);
        Gate::authorize('manageGuardians', $learner);
        $this->guardians->unlink($this->relationship($request), $this->actor($request));

        return $this->noContent();
    }

    public function recordConsent(StoreLearnerConsentRequest $request): JsonResponse
    {
        $learner = $this->learner($request);
        $guardian = $request->validated('guardian_uuid')
            ? GuardianProfile::query()->where('organization_id', $learner->getAttribute('organization_id'))->where('uuid', $request->validated('guardian_uuid'))->firstOrFail()
            : null;
        $consent = $this->guardians->recordConsent($learner, $guardian, $this->actor($request), $request->validated());

        return $this->created(['uuid' => $consent->getAttribute('uuid'), 'consent_type' => $consent->getAttribute('consent_type'), 'status' => $consent->getAttribute('status'), 'recorded_date' => $consent->getAttribute('recorded_date')->toDateString(), 'expiry_date' => $consent->getAttribute('expiry_date')?->toDateString()]);
    }

    private function relationship(Request $request): LearnerGuardianRelationship
    {
        $learner = $this->learner($request);
        $relationship = LearnerGuardianRelationship::query()->where('organization_id', $learner->getAttribute('organization_id'))->where('learner_profile_id', $learner->getKey())->where('uuid', (string) $request->route('relationship'))->firstOrFail();

        return $relationship;
    }

    private function guardian(Request $request): GuardianProfile
    {
        $guardian = $request->route('guardian');
        abort_unless($guardian instanceof GuardianProfile, 404);

        return $guardian;
    }

    private function learner(Request $request): LearnerProfile
    {
        $learner = $request->route('learner');
        abort_unless($learner instanceof LearnerProfile, 404);

        return $learner;
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private function organization(Request $request): Organization
    {
        $organization = $request->attributes->get('organization');
        abort_unless($organization instanceof Organization, 403);

        return $organization;
    }
}
