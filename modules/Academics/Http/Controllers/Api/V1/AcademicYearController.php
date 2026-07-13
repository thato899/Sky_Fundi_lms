<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Academics\Application\AcademicYearService;
use Modules\Academics\Http\Requests\StoreAcademicYearRequest;
use Modules\Academics\Http\Requests\UpdateAcademicYearRequest;
use Modules\Academics\Http\Resources\AcademicYearResource;
use Modules\Academics\Infrastructure\Models\AcademicYear;

final class AcademicYearController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AcademicYearService $academicYears,
    ) {}

    public function index(): JsonResponse
    {
        return $this->ok(AcademicYearResource::collection(
            AcademicYear::query()->orderByDesc('start_date')->get(),
        ));
    }

    public function store(StoreAcademicYearRequest $request): JsonResponse
    {
        return $this->created(new AcademicYearResource($this->academicYears->create($request->validated())));
    }

    public function show(AcademicYear $academicYear): JsonResponse
    {
        return $this->ok(new AcademicYearResource($academicYear->load('terms')));
    }

    public function update(UpdateAcademicYearRequest $request, AcademicYear $academicYear): JsonResponse
    {
        return $this->ok(new AcademicYearResource($this->academicYears->update($academicYear, $request->validated())));
    }

    public function setCurrent(AcademicYear $academicYear): JsonResponse
    {
        return $this->ok(new AcademicYearResource($this->academicYears->setCurrent($academicYear)));
    }

    public function close(AcademicYear $academicYear): JsonResponse
    {
        return $this->ok(new AcademicYearResource($this->academicYears->close($academicYear)));
    }

    public function archive(AcademicYear $academicYear): JsonResponse
    {
        return $this->ok(new AcademicYearResource($this->academicYears->archive($academicYear)));
    }
}
