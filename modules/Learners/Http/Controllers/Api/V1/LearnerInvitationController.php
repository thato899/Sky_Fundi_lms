<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Learners\Application\LearnerInvitationService;
use Modules\Learners\Http\Requests\StoreLearnerInvitationRequest;
use Modules\Learners\Http\Resources\LearnerInvitationResource;
use Modules\Learners\Infrastructure\Models\LearnerProfile;

final class LearnerInvitationController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly LearnerInvitationService $invitations) {}

    public function index(mixed $learner): JsonResponse
    {
        $learner = $this->learner($learner);
        Gate::authorize('viewInvitations', $learner);
        $membership = $learner->organizationMembership;

        return $this->ok($membership instanceof Membership ? [new LearnerInvitationResource($membership)] : []);
    }

    public function store(StoreLearnerInvitationRequest $request, mixed $learner): JsonResponse
    {
        $learner = $this->learner($learner);
        Gate::authorize('invite', $learner);
        $result = $this->invitations->invite($learner, $this->actor($request), (string) $request->validated('email'));

        return $this->created(new LearnerInvitationResource($result['membership']));
    }

    public function resend(Request $request, mixed $learner, string $invitation): JsonResponse
    {
        $learner = $this->learner($learner);
        Gate::authorize('invite', $learner);
        $membership = $this->membership($learner, $invitation);
        $result = $this->invitations->resend($membership, $learner, $this->actor($request));

        return $this->ok(new LearnerInvitationResource($result['membership']));
    }

    public function revoke(Request $request, mixed $learner, string $invitation): JsonResponse
    {
        $learner = $this->learner($learner);
        Gate::authorize('revokeInvitation', $learner);

        return $this->ok(new LearnerInvitationResource(
            $this->invitations->revoke($this->membership($learner, $invitation), $learner),
        ));
    }

    private function membership(LearnerProfile $learner, string $id): Membership
    {
        return Membership::query()
            ->whereKey($id)
            ->where('organization_id', $learner->organization_id)
            ->whereKey($learner->organization_membership_id)
            ->firstOrFail();
    }

    private function learner(mixed $learner): LearnerProfile
    {
        abort_unless($learner instanceof LearnerProfile, 404);

        return $learner;
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}
