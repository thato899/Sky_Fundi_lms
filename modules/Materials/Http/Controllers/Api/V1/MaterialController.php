<?php

declare(strict_types=1);

namespace Modules\Materials\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Materials\Application\MaterialIngestionService;
use Modules\Materials\Http\Requests\StoreMaterialRequest;
use Modules\Materials\Http\Resources\MaterialResource;
use Modules\Materials\Infrastructure\Models\Material;
use Modules\Organizations\Infrastructure\Models\Organization;

final class MaterialController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly MaterialIngestionService $ingestion) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Material::class);
        $organization = $this->organization($request);
        $materials = Material::query()
            ->where('organization_id', $organization->getKey())
            ->withCount('chunks')
            ->with('subject')
            ->latest()
            ->paginate((int) $request->integer('per_page', 25));

        return $this->ok(MaterialResource::collection($materials));
    }

    public function store(StoreMaterialRequest $request): JsonResponse
    {
        $organization = $this->organization($request);
        $material = $this->ingestion->ingest($organization, $this->actor($request), $request->validated());

        return $this->created(new MaterialResource($material));
    }

    public function show(Request $request, Material $material): JsonResponse
    {
        $organization = $this->organization($request);
        abort_unless($material->organization_id === $organization->getKey(), 404);
        Gate::authorize('view', $material);

        return $this->ok(new MaterialResource($material->loadCount('chunks')->load('subject')));
    }

    public function destroy(Request $request, Material $material): JsonResponse
    {
        $organization = $this->organization($request);
        abort_unless($material->organization_id === $organization->getKey(), 404);
        Gate::authorize('delete', $material);
        $this->ingestion->delete($material);

        return $this->noContent();
    }

    private function organization(Request $request): Organization
    {
        $organization = $request->attributes->get('organization');
        abort_unless($organization instanceof Organization, 403);

        return $organization;
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}
