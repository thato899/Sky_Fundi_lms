<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Academics\Application\TimetableService;
use Modules\Academics\Domain\Enums\DayOfWeek;
use Modules\Academics\Http\Requests\StoreTimetablePeriodRequest;
use Modules\Academics\Http\Requests\UpdateTimetablePeriodRequest;
use Modules\Academics\Http\Resources\TimetablePeriodResource;
use Modules\Academics\Infrastructure\Models\TimetablePeriod;

final class TimetablePeriodController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly TimetableService $timetable,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $day = $request->string('day_of_week')->value();

        $periods = $day !== ''
            ? $this->timetable->periodsForDay(DayOfWeek::from($day))
            : TimetablePeriod::query()->orderBy('day_of_week')->orderBy('order')->get();

        return $this->ok(TimetablePeriodResource::collection($periods));
    }

    public function store(StoreTimetablePeriodRequest $request): JsonResponse
    {
        return $this->created(new TimetablePeriodResource($this->timetable->createPeriod($request->validated())));
    }

    public function update(UpdateTimetablePeriodRequest $request, TimetablePeriod $timetablePeriod): JsonResponse
    {
        return $this->ok(new TimetablePeriodResource($this->timetable->updatePeriod($timetablePeriod, $request->validated())));
    }
}
