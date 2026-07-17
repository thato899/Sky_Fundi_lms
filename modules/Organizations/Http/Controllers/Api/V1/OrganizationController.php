<?php

declare(strict_types=1);

namespace Modules\Organizations\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Modules\Organizations\Application\OrganizationService;
use Modules\Organizations\Http\Requests\AssignOrganizationAdministratorRequest;
use Modules\Organizations\Http\Requests\IndexOrganizationRequest;
use Modules\Organizations\Http\Requests\StoreOrganizationRequest;
use Modules\Organizations\Http\Requests\UpdateOrganizationAiRequest;
use Modules\Organizations\Http\Requests\UpdateOrganizationModuleRequest;
use Modules\Organizations\Http\Requests\UpdateOrganizationRequest;
use Modules\Organizations\Http\Requests\UpdateOrganizationSettingsRequest;
use Modules\Organizations\Http\Resources\OrganizationResource;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Infrastructure\Repositories\OrganizationRepository;

final class OrganizationController extends Controller
{
    use ApiResponse;
    use AuthorizesRequests;

    public function __construct(private readonly OrganizationService $organizations, private readonly OrganizationRepository $repository) {}

    public function index(IndexOrganizationRequest $request): JsonResponse
    {
        return $this->ok(OrganizationResource::collection($this->repository->paginate($request->validated(), $request->user()?->can('organizations.manage') ? null : $request->user()?->id)));
    }

    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        return $this->created(new OrganizationResource($this->organizations->create($request->validated(), $request->user()?->id)));
    }

    public function show(Organization $organization): JsonResponse
    {
        $this->authorize('view', $organization);

        return $this->ok(new OrganizationResource($organization->load(['administrators', 'modules', 'aiConfiguration'])));
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): JsonResponse
    {
        $this->authorize('update', $organization);

        return $this->ok(new OrganizationResource($this->organizations->update($organization, $request->validated(), $request->user()?->id)));
    }

    public function destroy(Organization $organization): JsonResponse
    {
        $this->authorize('delete', $organization);
        $this->organizations->delete($organization);

        return $this->noContent();
    }

    public function activate(Organization $organization): JsonResponse
    {
        $this->authorize('update', $organization);

        return $this->ok(new OrganizationResource($this->organizations->activate($organization)));
    }

    public function suspend(Organization $organization): JsonResponse
    {
        $this->authorize('update', $organization);

        return $this->ok(new OrganizationResource($this->organizations->suspend($organization)));
    }

    public function assignAdministrator(AssignOrganizationAdministratorRequest $request, Organization $organization): JsonResponse
    {
        $this->authorize('update', $organization);
        $this->organizations->assignAdministrator($organization, $request->validated('user_id'), $request->user()?->id);

        return $this->ok(new OrganizationResource($organization->fresh()->load('administrators')));
    }

    public function settings(Organization $organization): JsonResponse
    {
        $this->authorize('view', $organization);

        return $this->ok($this->organizations->settings($organization));
    }

    public function updateSettings(UpdateOrganizationSettingsRequest $request, Organization $organization): JsonResponse
    {
        $this->authorize('update', $organization);

        return $this->ok($this->organizations->updateSettings($organization, $request->validated('settings')));
    }

    public function branding(Organization $organization): JsonResponse
    {
        $this->authorize('view', $organization);

        return $this->ok($this->organizations->branding($organization));
    }

    public function updateBranding(UpdateOrganizationSettingsRequest $request, Organization $organization): JsonResponse
    {
        $this->authorize('update', $organization);

        return $this->ok($this->organizations->updateBranding($organization, $request->validated('settings.branding') ?? []));
    }

    public function configureAi(UpdateOrganizationAiRequest $request, Organization $organization): JsonResponse
    {
        $this->authorize('update', $organization);

        return $this->ok($this->organizations->configureAi($organization, $request->validated())->toArray());
    }

    public function setModule(UpdateOrganizationModuleRequest $request, Organization $organization): JsonResponse
    {
        $this->authorize('update', $organization);
        $data = $request->validated();

        return $this->ok($this->organizations->setModule($organization, $data['module_name'], $data['enabled'], $request->user()?->id)->toArray());
    }
}
