<?php

declare(strict_types=1);

namespace Modules\Assessments\Http\Controllers\Api\V1;

use Core\Users\Infrastructure\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Assessments\Application\AssessmentCategoryService;
use Modules\Assessments\Infrastructure\Models\AssessmentCategory;
use Modules\Organizations\Infrastructure\Models\Organization;

final class AssessmentCategoryController
{
    public function __construct(private readonly AssessmentCategoryService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AssessmentCategory::class);

        return response()->json(['data' => AssessmentCategory::query()->where('organization_id', $this->organization($request)->getKey())->orderBy('display_order')->paginate(50)]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('manageCategories', AssessmentCategory::class);

        return response()->json(['data' => $this->service->create($this->organization($request), $this->actor($request), $this->validate($request))], 201);
    }

    public function update(Request $request, AssessmentCategory $category): JsonResponse
    {
        Gate::authorize('manageCategories', $category);

        return response()->json(['data' => $this->service->update($category, $this->actor($request), $this->validate($request, true))]);
    }

    public function activate(Request $request, AssessmentCategory $category): JsonResponse
    {
        Gate::authorize('manageCategories', $category);

        return response()->json(['data' => $this->service->active($category, $this->actor($request), true)]);
    }

    public function deactivate(Request $request, AssessmentCategory $category): JsonResponse
    {
        Gate::authorize('manageCategories', $category);

        return response()->json(['data' => $this->service->active($category, $this->actor($request), false)]);
    }

    public function reorder(Request $request): JsonResponse
    {
        Gate::authorize('manageCategories', AssessmentCategory::class);
        $data = $request->validate(['category_uuids' => ['required', 'array'], 'category_uuids.*' => ['uuid', 'distinct']]);
        $this->service->reorder($this->organization($request), $this->actor($request), $data['category_uuids']);

        return response()->json(['message' => 'Categories reordered.']);
    }

    private function validate(Request $request, bool $partial = false): array
    {
        return $request->validate(['organization_id' => ['prohibited'], 'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'], 'code' => ['nullable', 'string', 'max:64'], 'description' => ['nullable', 'string', 'max:2000'], 'default_weighting' => ['nullable', 'numeric', 'between:0,100'], 'display_order' => ['nullable', 'integer', 'min:0']]);
    }

    private function actor(Request $r): User
    {
        $u = $r->user();
        abort_unless($u instanceof User, 401);

        return $u;
    }

    private function organization(Request $r): Organization
    {
        $o = $r->attributes->get('organization');
        abort_unless($o instanceof Organization, 403);

        return $o;
    }
}
