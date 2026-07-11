<?php

declare(strict_types=1);

namespace Core\RBAC\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\RBAC\Application\RoleService;
use Core\RBAC\Http\Requests\AssignRoleRequest;
use Core\RBAC\Http\Requests\StoreRoleRequest;
use Core\RBAC\Http\Requests\SyncRolePermissionsRequest;
use Core\RBAC\Http\Resources\RoleResource;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Deliberately thin — see docs/architecture/clean-architecture.md.
 * All business logic lives in RoleService.
 */
final class RoleController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly RoleService $roles,
    ) {}

    public function index(): JsonResponse
    {
        $roles = Role::query()->with('permissions')->orderBy('name')->get();

        return $this->ok(RoleResource::collection($roles));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->roles->createRole(
            name: $request->string('name')->value(),
            description: $request->string('description')->value() ?: null,
            permissionNames: $request->array('permissions'),
        );

        return $this->created(new RoleResource($role->load('permissions')));
    }

    public function show(Role $role): JsonResponse
    {
        return $this->ok(new RoleResource($role->load('permissions')));
    }

    public function syncPermissions(SyncRolePermissionsRequest $request, Role $role): JsonResponse
    {
        $role = $this->roles->syncPermissions($role, $request->array('permissions'));

        return $this->ok(new RoleResource($role));
    }

    public function assignToUser(AssignRoleRequest $request, User $user): JsonResponse
    {
        $role = Role::query()->findOrFail($request->string('role_id')->value());

        $this->roles->assignRoleToUser($user, $role);

        return $this->message('Role assigned.');
    }

    public function revokeFromUser(Request $request, User $user, Role $role): JsonResponse
    {
        $this->roles->revokeRoleFromUser($user, $role);

        return $this->noContent();
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->roles->deleteRole($role);

        return $this->noContent();
    }
}
