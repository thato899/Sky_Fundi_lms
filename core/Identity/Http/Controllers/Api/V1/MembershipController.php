<?php

declare(strict_types=1);

namespace Core\Identity\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Identity\Application\MembershipService;
use Core\Identity\Application\OrganizationContextService;
use Core\Identity\Application\PermissionResolver;
use Core\Identity\Domain\Enums\MembershipStatus;
use Core\Identity\Http\Resources\MembershipResource;
use Core\Identity\Infrastructure\Models\Membership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Organizations\Application\OrganizationService;
use Modules\Organizations\Infrastructure\Models\Organization;

final class MembershipController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly MembershipService $memberships, private readonly OrganizationContextService $context, private readonly PermissionResolver $permissions) {}

    public function index(Request $request): JsonResponse
    {
        return $this->ok(MembershipResource::collection(Membership::query()->where('user_id', $request->user()->id)->with('role')->get()));
    }

    public function invite(Request $request): JsonResponse
    {
        $data = $request->validate(['user_id' => ['required', 'uuid', 'exists:users,id'], 'organization_id' => ['required', 'uuid', 'exists:organizations,id'], 'role_id' => ['nullable', 'uuid', 'exists:roles,id']]);
        $organization = Organization::query()->findOrFail($data['organization_id']);
        $isPlatformManager = $request->user()->can('organizations.manage');
        $isOrganizationAdministrator = $organization->administrators()->whereKey($request->user()->getKey())->exists();

        abort_unless($isPlatformManager || $isOrganizationAdministrator, 403);

        return $this->created(new MembershipResource($this->memberships->invite($data, $request->user()->id)));
    }

    public function accept(Membership $membership): JsonResponse
    {
        abort_unless($membership->user_id === request()->user()->id, 403);

        return $this->ok(new MembershipResource($this->memberships->accept($membership)));
    }

    public function reject(Membership $membership): JsonResponse
    {
        abort_unless($membership->user_id === request()->user()->id, 403);

        return $this->ok(new MembershipResource($this->memberships->reject($membership)));
    }

    public function switch(Membership $membership): JsonResponse
    {
        abort_unless($membership->user_id === request()->user()->id && $membership->getAttribute('status') === MembershipStatus::Active, 403);

        return $this->ok(new MembershipResource($this->memberships->makeDefault($membership)));
    }

    public function current(Request $request): JsonResponse
    {
        $membership = $this->context->fromRequest($request);
        abort_unless($membership !== null, 404);
        $organization = $membership->getRelation('organization');
        abort_unless($organization instanceof Organization, 404);

        return $this->ok(['membership' => (new MembershipResource($membership))->resolve(), 'permissions' => $this->permissions->permissions($membership), 'modules' => $organization->modules->where('enabled', true)->pluck('module_name')->values(), 'branding' => app(OrganizationService::class)->branding($organization), 'ai_provider' => $organization->aiConfiguration?->provider]);
    }
}
