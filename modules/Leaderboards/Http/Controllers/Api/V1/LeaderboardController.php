<?php

declare(strict_types=1);

namespace Modules\Leaderboards\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Leaderboards\Application\LeaderboardService;
use Modules\Leaderboards\Http\Resources\LeaderboardEntryResource;
use Modules\Leaderboards\Http\Resources\LeaderboardResource;
use Modules\Leaderboards\Infrastructure\Models\Leaderboard;
use Modules\Leaderboards\Infrastructure\Models\LeaderboardEntry;
use Modules\Learners\Infrastructure\Models\GuardianProfile;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;

final class LeaderboardController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly LeaderboardService $leaderboards,
        private readonly PermissionResolver $permissions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        [$organization, $membership] = $this->context($request);
        $this->authorizeAny($membership, ['leaderboards.manage', 'leaderboards.view_organization']);

        return $this->ok(LeaderboardResource::collection(
            Leaderboard::query()->where('organization_id', $organization->getKey())->with(['subject', 'grade', 'classGroup'])->latest('generated_at')->get()
        ));
    }

    public function storeAcademic(Request $request): JsonResponse
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'leaderboards.manage'), 403);
        $data = $request->validate([
            'reporting_period_id' => ['required', 'uuid'],
            'subject_id' => ['nullable', 'uuid'],
            'grade_id' => ['nullable', 'uuid'],
            'class_id' => ['nullable', 'uuid'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);
        try {
            $leaderboard = $this->leaderboards->generateAcademic($organization, $this->actor($request), $data);
        } catch (DomainException $exception) {
            return $this->message($exception->getMessage(), 422);
        }

        return $this->created(new LeaderboardResource($leaderboard));
    }

    public function storeSports(Request $request): JsonResponse
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'leaderboards.manage'), 403);
        $data = $request->validate([
            'period_start_date' => ['required', 'date'],
            'period_end_date' => ['required', 'date', 'after_or_equal:period_start_date'],
            'grade_id' => ['nullable', 'uuid'],
            'class_id' => ['nullable', 'uuid'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);
        try {
            $leaderboard = $this->leaderboards->generateSports($organization, $this->actor($request), $data);
        } catch (DomainException $exception) {
            return $this->message($exception->getMessage(), 422);
        }

        return $this->created(new LeaderboardResource($leaderboard));
    }

    public function show(Request $request, Leaderboard $leaderboard): JsonResponse
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($leaderboard->getAttribute('organization_id') === $organization->getKey(), 404);
        $leaderboard->load(['entries.learner', 'subject', 'grade', 'classGroup']);

        $canViewFull = $this->permissions->allows($membership, 'leaderboards.manage') || $this->permissions->allows($membership, 'leaderboards.view_organization');
        $fullyVisible = $leaderboard->isPublished() || $canViewFull;
        $viewerLearnerIds = $this->viewerLearnerIds($request, $organization);
        $entries = $fullyVisible ? $leaderboard->entries : $leaderboard->entries->whereIn('learner_profile_id', $viewerLearnerIds)->values();
        $request->attributes->set('leaderboard_viewer_learner_ids', $viewerLearnerIds);

        return $this->ok([
            'leaderboard' => (new LeaderboardResource($leaderboard))->resolve(),
            'fully_visible' => $fullyVisible,
            'entries' => $entries->map(fn (LeaderboardEntry $entry) => (new LeaderboardEntryResource($entry, $fullyVisible))->resolve())->values(),
        ]);
    }

    public function mine(Request $request): JsonResponse
    {
        [$organization] = $this->context($request);
        $learnerIds = $this->viewerLearnerIds($request, $organization);
        abort_if($learnerIds === [], 403);
        $request->attributes->set('leaderboard_viewer_learner_ids', $learnerIds);

        $entries = LeaderboardEntry::query()->where('organization_id', $organization->getKey())->whereIn('learner_profile_id', $learnerIds)->with(['leaderboard', 'learner'])->get();

        return $this->ok($entries->map(fn (LeaderboardEntry $entry) => [
            'leaderboard' => (new LeaderboardResource($entry->leaderboard))->resolve(),
            'entry' => (new LeaderboardEntryResource($entry, false))->resolve(),
        ])->values());
    }

    public function publish(Request $request, Leaderboard $leaderboard): JsonResponse
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'leaderboards.manage'), 403);
        abort_unless($leaderboard->getAttribute('organization_id') === $organization->getKey(), 404);

        return $this->ok(new LeaderboardResource($this->leaderboards->publish($leaderboard, $this->actor($request))));
    }

    public function unpublish(Request $request, Leaderboard $leaderboard): JsonResponse
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'leaderboards.manage'), 403);
        abort_unless($leaderboard->getAttribute('organization_id') === $organization->getKey(), 404);

        return $this->ok(new LeaderboardResource($this->leaderboards->unpublish($leaderboard, $this->actor($request))));
    }

    /** @return list<string> */
    private function viewerLearnerIds(Request $request, Organization $organization): array
    {
        $user = $this->actor($request);
        $ownLearner = LearnerProfile::query()->where('organization_id', $organization->getKey())->where('user_id', $user->getKey())->first();
        if ($ownLearner !== null) {
            return [$ownLearner->getKey()];
        }
        $guardian = GuardianProfile::query()->where('organization_id', $organization->getKey())->where('user_id', $user->getKey())->first();
        if ($guardian !== null) {
            return $guardian->relationships()->where('status', 'active')->pluck('learner_profile_id')->all();
        }

        return [];
    }

    private function authorizeAny(Membership $membership, array $anyOf): void
    {
        foreach ($anyOf as $permission) {
            if ($this->permissions->allows($membership, $permission)) {
                return;
            }
        }
        abort(403);
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
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
