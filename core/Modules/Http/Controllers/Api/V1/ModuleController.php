<?php

declare(strict_types=1);

namespace Core\Modules\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Modules\Application\ModuleManager;
use Core\Modules\Http\Requests\EnableModuleRequest;
use Core\Modules\Http\Requests\InstallModuleRequest;
use Core\Modules\Http\Resources\ModuleResource;
use Core\Modules\Infrastructure\Models\ModuleRegistration;
use Illuminate\Http\JsonResponse;

/**
 * Deliberately thin — see docs/architecture/clean-architecture.md.
 * All lifecycle logic lives in ModuleManager.
 */
final class ModuleController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ModuleManager $modules,
    ) {}

    public function index(): JsonResponse
    {
        $installed = ModuleRegistration::query()->orderBy('name')->get();

        return $this->ok([
            'installed' => ModuleResource::collection($installed),
            'discovered' => $this->modules->discover(),
        ]);
    }

    public function install(InstallModuleRequest $request): JsonResponse
    {
        $module = $this->modules->install($request->string('name')->value());

        return $this->created(new ModuleResource($module));
    }

    public function enable(EnableModuleRequest $request, string $name): JsonResponse
    {
        $module = $this->modules->enable($name, $request->string('tenant_id')->value() ?: null);

        return $this->ok(new ModuleResource($module));
    }

    public function disable(EnableModuleRequest $request, string $name): JsonResponse
    {
        $module = $this->modules->disable($name, $request->string('tenant_id')->value() ?: null);

        return $this->ok(new ModuleResource($module));
    }

    public function destroy(string $name): JsonResponse
    {
        $this->modules->remove($name);

        return $this->noContent();
    }
}
