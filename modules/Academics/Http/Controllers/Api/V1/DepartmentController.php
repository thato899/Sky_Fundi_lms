<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Academics\Application\DepartmentService;
use Modules\Academics\Http\Requests\StoreDepartmentRequest;
use Modules\Academics\Http\Requests\UpdateDepartmentRequest;
use Modules\Academics\Http\Resources\DepartmentResource;
use Modules\Academics\Infrastructure\Models\Department;

final class DepartmentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly DepartmentService $departments,
    ) {}

    public function index(): JsonResponse
    {
        return $this->ok(DepartmentResource::collection(Department::query()->orderBy('name')->get()));
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        return $this->created(new DepartmentResource($this->departments->create($request->validated())));
    }

    public function show(Department $department): JsonResponse
    {
        return $this->ok(new DepartmentResource($department));
    }

    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        return $this->ok(new DepartmentResource($this->departments->update($department, $request->validated())));
    }
}
