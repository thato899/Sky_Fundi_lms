<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Academics\Application\AcademicTermService;
use Modules\Academics\Http\Requests\StoreAcademicTermRequest;
use Modules\Academics\Http\Requests\UpdateAcademicTermRequest;
use Modules\Academics\Http\Resources\AcademicTermResource;
use Modules\Academics\Infrastructure\Models\AcademicTerm;
use Modules\Academics\Infrastructure\Models\AcademicYear;

/**
 * Nested under an AcademicYear (/academic-years/{academicYear}/terms)
 * since a term only ever makes sense relative to its year.
 */
final class AcademicTermController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AcademicTermService $terms,
    ) {}

    public function index(AcademicYear $academicYear): JsonResponse
    {
        return $this->ok(AcademicTermResource::collection($academicYear->terms()->orderBy('term_number')->get()));
    }

    public function store(StoreAcademicTermRequest $request, AcademicYear $academicYear): JsonResponse
    {
        return $this->created(new AcademicTermResource($this->terms->create($academicYear, $request->validated())));
    }

    public function update(UpdateAcademicTermRequest $request, AcademicYear $academicYear, AcademicTerm $term): JsonResponse
    {
        return $this->ok(new AcademicTermResource($this->terms->update($term, $request->validated())));
    }

    public function setCurrent(AcademicYear $academicYear, AcademicTerm $term): JsonResponse
    {
        return $this->ok(new AcademicTermResource($this->terms->setCurrent($term)));
    }
}
