<?php

declare(strict_types=1);

namespace Core\RBAC\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\RBAC\Http\Resources\PermissionResource;
use Core\RBAC\Infrastructure\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only listing of the permission catalog. Permissions themselves
 * are registered by Core (see database/seeders/PermissionSeeder.php)
 * and by module manifests (see
 * docs/architecture/module-system.md#module-manifest-modulejson) —
 * never created ad hoc through this API.
 */
final class PermissionController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $permissions = Permission::query()
            ->when($request->string('module')->isNotEmpty(), fn ($q) => $q->where('module', $request->string('module')->value()))
            ->orderBy('module')
            ->orderBy('name')
            ->get();

        return $this->ok(PermissionResource::collection($permissions));
    }
}
