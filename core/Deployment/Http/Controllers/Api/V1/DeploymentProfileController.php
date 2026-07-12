<?php

declare(strict_types=1);

namespace Core\Deployment\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Deployment\Application\DeploymentProfileService;
use Core\Deployment\Http\Requests\StoreDeploymentProfileRequest;
use Core\Deployment\Http\Resources\DeploymentProfileResource;
use Core\Deployment\Infrastructure\Models\DeploymentProfile;
use Illuminate\Http\JsonResponse;

final class DeploymentProfileController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly DeploymentProfileService $profiles,
    ) {}

    public function index(): JsonResponse
    {
        return $this->ok(DeploymentProfileResource::collection(
            DeploymentProfile::query()->latest()->paginate(25),
        ));
    }

    public function store(StoreDeploymentProfileRequest $request): JsonResponse
    {
        return $this->created(new DeploymentProfileResource($this->profiles->create($request->validated())));
    }

    public function show(DeploymentProfile $deploymentProfile): JsonResponse
    {
        return $this->ok(new DeploymentProfileResource($deploymentProfile));
    }

    public function update(StoreDeploymentProfileRequest $request, DeploymentProfile $deploymentProfile): JsonResponse
    {
        return $this->ok(new DeploymentProfileResource($this->profiles->update($deploymentProfile, $request->validated())));
    }
}
