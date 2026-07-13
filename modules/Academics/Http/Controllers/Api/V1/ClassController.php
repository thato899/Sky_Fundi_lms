<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Academics\Application\ClassService;
use Modules\Academics\Http\Requests\StoreClassRequest;
use Modules\Academics\Http\Requests\UpdateClassRequest;
use Modules\Academics\Http\Resources\ClassResource;
use Modules\Academics\Infrastructure\Models\ClassGroup;

final class ClassController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ClassService $classes,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $classes = ClassGroup::query()
            ->when($request->string('academic_year_id')->isNotEmpty(), fn ($q) => $q->where('academic_year_id', $request->string('academic_year_id')->value()))
            ->when($request->string('grade_id')->isNotEmpty(), fn ($q) => $q->where('grade_id', $request->string('grade_id')->value()))
            ->orderBy('name')
            ->get();

        return $this->ok(ClassResource::collection($classes));
    }

    public function store(StoreClassRequest $request): JsonResponse
    {
        return $this->created(new ClassResource($this->classes->create($request->validated())));
    }

    public function show(ClassGroup $class): JsonResponse
    {
        return $this->ok(new ClassResource($class));
    }

    public function update(UpdateClassRequest $request, ClassGroup $class): JsonResponse
    {
        return $this->ok(new ClassResource($this->classes->update($class, $request->validated())));
    }
}
