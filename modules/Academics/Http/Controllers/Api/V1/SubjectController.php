<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Academics\Application\SubjectService;
use Modules\Academics\Http\Requests\AssignCurriculumRequest;
use Modules\Academics\Http\Requests\AssignDepartmentRequest;
use Modules\Academics\Http\Requests\StoreSubjectRequest;
use Modules\Academics\Http\Requests\UpdateSubjectRequest;
use Modules\Academics\Http\Resources\SubjectResource;
use Modules\Academics\Infrastructure\Models\Curriculum;
use Modules\Academics\Infrastructure\Models\Department;
use Modules\Academics\Infrastructure\Models\Subject;

final class SubjectController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly SubjectService $subjects,
    ) {}

    public function index(): JsonResponse
    {
        return $this->ok(SubjectResource::collection(Subject::query()->orderBy('name')->get()));
    }

    public function store(StoreSubjectRequest $request): JsonResponse
    {
        return $this->created(new SubjectResource($this->subjects->create($request->validated())));
    }

    public function show(Subject $subject): JsonResponse
    {
        return $this->ok(new SubjectResource($subject));
    }

    public function update(UpdateSubjectRequest $request, Subject $subject): JsonResponse
    {
        return $this->ok(new SubjectResource($this->subjects->update($subject, $request->validated())));
    }

    public function assignCurriculum(AssignCurriculumRequest $request, Subject $subject): JsonResponse
    {
        $curriculum = Curriculum::query()->findOrFail($request->string('curriculum_id')->value());

        return $this->ok(new SubjectResource($this->subjects->assignCurriculum($subject, $curriculum)));
    }

    public function assignDepartment(AssignDepartmentRequest $request, Subject $subject): JsonResponse
    {
        $department = Department::query()->findOrFail($request->string('department_id')->value());

        return $this->ok(new SubjectResource($this->subjects->assignDepartment($subject, $department)));
    }
}
