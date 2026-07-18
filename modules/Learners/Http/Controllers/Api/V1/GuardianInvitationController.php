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
use Modules\Learners\Application\GuardianInvitationService;
use Modules\Learners\Http\Requests\StoreGuardianInvitationRequest;
use Modules\Learners\Http\Resources\GuardianInvitationResource;
use Modules\Learners\Infrastructure\Models\GuardianProfile;

final class GuardianInvitationController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly GuardianInvitationService $invitations) {}

    public function index(mixed $guardian): JsonResponse
    {
        $guardian = $this->guardian($guardian);
        Gate::authorize('viewInvitations', $guardian);
        $membership = $guardian->organizationMembership;

        return $this->ok($membership instanceof Membership ? [new GuardianInvitationResource($membership)] : []);
    }

    public function store(StoreGuardianInvitationRequest $request, mixed $guardian): JsonResponse
    {
        $guardian = $this->guardian($guardian);
        Gate::authorize('invite', $guardian);
        $result = $this->invitations->invite($guardian, $this->actor($request), (string) $request->validated('email'));

        return $this->created(new GuardianInvitationResource($result['membership']));
    }

    public function resend(Request $request, mixed $guardian, string $invitation): JsonResponse
    {
        $guardian = $this->guardian($guardian);
        Gate::authorize('invite', $guardian);
        $membership = $this->membership($guardian, $invitation);
        $result = $this->invitations->resend($membership, $guardian, $this->actor($request));

        return $this->ok(new GuardianInvitationResource($result['membership']));
    }

    public function revoke(Request $request, mixed $guardian, string $invitation): JsonResponse
    {
        $guardian = $this->guardian($guardian);
        Gate::authorize('revokeInvitation', $guardian);

        return $this->ok(new GuardianInvitationResource(
            $this->invitations->revoke($this->membership($guardian, $invitation), $guardian),
        ));
    }

    private function membership(GuardianProfile $guardian, string $id): Membership
    {
        return Membership::query()
            ->whereKey($id)
            ->where('organization_id', $guardian->organization_id)
            ->whereKey($guardian->organization_membership_id)
            ->firstOrFail();
    }

    private function guardian(mixed $guardian): GuardianProfile
    {
        abort_unless($guardian instanceof GuardianProfile, 404);

        return $guardian;
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}
