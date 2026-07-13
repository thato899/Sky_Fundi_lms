<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Academics\Application\CurriculumService;
use Modules\Academics\Http\Requests\StoreCurriculumRequest;
use Modules\Academics\Http\Requests\UpdateCurriculumRequest;
use Modules\Academics\Http\Resources\CurriculumResource;
use Modules\Academics\Infrastructure\Models\Curriculum;

final class CurriculumController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CurriculumService $curricula,
    ) {}

    public function index(): JsonResponse
    {
        return $this->ok(CurriculumResource::collection(Curriculum::query()->orderBy('name')->get()));
    }

    public function store(StoreCurriculumRequest $request): JsonResponse
    {
        return $this->created(new CurriculumResource($this->curricula->create($request->validated())));
    }

    public function show(Curriculum $curriculum): JsonResponse
    {
        return $this->ok(new CurriculumResource($curriculum));
    }

    public function update(UpdateCurriculumRequest $request, Curriculum $curriculum): JsonResponse
    {
        return $this->ok(new CurriculumResource($this->curricula->update($curriculum, $request->validated())));
    }

    public function deactivate(Curriculum $curriculum): JsonResponse
    {
        return $this->ok(new CurriculumResource($this->curricula->deactivate($curriculum)));
    }
}
