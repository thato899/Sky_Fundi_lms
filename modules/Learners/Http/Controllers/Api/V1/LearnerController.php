<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Learners\Application\LearnerDirectoryService;
use Modules\Learners\Application\LearnerService;
use Modules\Learners\Domain\Enums\LearnerStatus;
use Modules\Learners\Http\Requests\ArchiveLearnerRequest;
use Modules\Learners\Http\Requests\LearnerIndexRequest;
use Modules\Learners\Http\Requests\RestoreLearnerRequest;
use Modules\Learners\Http\Requests\StoreLearnerRequest;
use Modules\Learners\Http\Requests\TransitionLearnerStatusRequest;
use Modules\Learners\Http\Requests\UpdateAcademicPlacementRequest;
use Modules\Learners\Http\Requests\UpdateLearnerRequest;
use Modules\Learners\Http\Resources\LearnerResource;
use Modules\Learners\Http\Resources\LearnerStatusHistoryResource;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;

final class LearnerController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly LearnerService $learners,
        private readonly LearnerDirectoryService $directory,
    ) {}

    public function index(LearnerIndexRequest $request): JsonResponse
    {
        return $this->ok(LearnerResource::collection($this->directory->paginate($this->organization($request), $request->validated())));
    }

    public function store(StoreLearnerRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $manualAllowed = ! $request->has('learner_number') || $user->can('overrideNumber', LearnerProfile::class);

        return $this->created(new LearnerResource($this->learners->create($this->organization($request), $user, $request->validated(), $manualAllowed)));
    }

    public function show(Request $request): JsonResponse
    {
        $learner = $this->learner($request);
        Gate::authorize('view', $learner);

        return $this->ok(new LearnerResource($learner->load(['currentAcademicYear', 'currentGrade', 'currentClass', 'curriculum'])));
    }

    public function update(UpdateLearnerRequest $request): JsonResponse
    {
        return $this->ok(new LearnerResource($this->learners->update($this->learner($request), $this->actor($request), $request->validated())));
    }

    public function academicPlacement(UpdateAcademicPlacementRequest $request): JsonResponse
    {
        return $this->ok(new LearnerResource($this->learners->updateAcademicPlacement($this->learner($request), $this->actor($request), $request->validated())));
    }

    public function status(TransitionLearnerStatusRequest $request): JsonResponse
    {
        return $this->ok(new LearnerResource($this->learners->transition(
            $this->learner($request),
            $this->actor($request),
            LearnerStatus::from($request->validated('status')),
            $request->validated('reason'),
        )));
    }

    public function archive(ArchiveLearnerRequest $request): JsonResponse
    {
        return $this->ok(new LearnerResource($this->learners->archive($this->learner($request), $this->actor($request), $request->validated('reason'))));
    }

    public function restore(RestoreLearnerRequest $request): JsonResponse
    {
        return $this->ok(new LearnerResource($this->learners->restore($this->learner($request), $this->actor($request), $request->validated('reason'))));
    }

    public function statusHistory(Request $request): JsonResponse
    {
        $learner = $this->learner($request);
        Gate::authorize('viewStatusHistory', $learner);

        return $this->ok(LearnerStatusHistoryResource::collection($learner->statusHistory()->with('actor')->orderByDesc('changed_at')->orderByDesc('id')->get()));
    }

    private function learner(Request $request): LearnerProfile
    {
        $learner = $request->route('learner');
        abort_unless($learner instanceof LearnerProfile, 404);

        return $learner;
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function organization(LearnerIndexRequest|StoreLearnerRequest $request): Organization
    {
        $organization = $request->attributes->get('organization');
        abort_unless($organization instanceof Organization, 403);

        return $organization;
    }
}
