<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Academics\Application\GradeService;
use Modules\Academics\Http\Requests\AssignCurriculumRequest;
use Modules\Academics\Http\Requests\ReorderGradesRequest;
use Modules\Academics\Http\Requests\StoreGradeRequest;
use Modules\Academics\Http\Requests\UpdateGradeRequest;
use Modules\Academics\Http\Resources\GradeResource;
use Modules\Academics\Infrastructure\Models\Curriculum;
use Modules\Academics\Infrastructure\Models\Grade;

final class GradeController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly GradeService $grades,
    ) {}

    public function index(): JsonResponse
    {
        return $this->ok(GradeResource::collection(Grade::query()->orderBy('order')->get()));
    }

    public function store(StoreGradeRequest $request): JsonResponse
    {
        return $this->created(new GradeResource($this->grades->create($request->validated())));
    }

    public function show(Grade $grade): JsonResponse
    {
        return $this->ok(new GradeResource($grade));
    }

    public function update(UpdateGradeRequest $request, Grade $grade): JsonResponse
    {
        return $this->ok(new GradeResource($this->grades->update($grade, $request->validated())));
    }

    public function assignCurriculum(AssignCurriculumRequest $request, Grade $grade): JsonResponse
    {
        $curriculum = Curriculum::query()->findOrFail($request->string('curriculum_id')->value());

        return $this->ok(new GradeResource($this->grades->assignCurriculum($grade, $curriculum)));
    }

    public function reorder(ReorderGradesRequest $request): JsonResponse
    {
        $this->grades->reorder($request->array('grade_ids'));

        return $this->message('Grades reordered.');
    }
}
