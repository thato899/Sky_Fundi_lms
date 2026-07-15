<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Academics\Application\CalendarService;
use Modules\Academics\Domain\Enums\CalendarEntryType;
use Modules\Academics\Http\Requests\StoreCalendarEntryRequest;
use Modules\Academics\Http\Requests\UpdateCalendarEntryRequest;
use Modules\Academics\Http\Resources\CalendarEntryResource;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\CalendarEntry;

/**
 * Nested under an AcademicYear (/academic-years/{academicYear}/calendar-entries)
 * — see modules/Academics/README.md#calendar.
 */
final class CalendarEntryController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CalendarService $calendar,
    ) {}

    public function index(Request $request, AcademicYear $academicYear): JsonResponse
    {
        $type = $request->string('type')->value();

        $entries = $type !== ''
            ? $this->calendar->entriesOfType($academicYear, CalendarEntryType::from($type))
            : $academicYear->calendarEntries()->orderBy('start_date')->get();

        return $this->ok(CalendarEntryResource::collection($entries));
    }

    public function store(StoreCalendarEntryRequest $request, AcademicYear $academicYear): JsonResponse
    {
        return $this->created(new CalendarEntryResource($this->calendar->addEntry($academicYear, $request->validated())));
    }

    public function update(UpdateCalendarEntryRequest $request, AcademicYear $academicYear, CalendarEntry $entry): JsonResponse
    {
        abort_unless($entry->getAttribute('academic_year_id') === $academicYear->getKey(), 404);

        return $this->ok(new CalendarEntryResource($this->calendar->updateEntry($entry, $request->validated())));
    }

    public function destroy(AcademicYear $academicYear, CalendarEntry $entry): JsonResponse
    {
        abort_unless($entry->getAttribute('academic_year_id') === $academicYear->getKey(), 404);
        $this->calendar->removeEntry($entry);

        return $this->noContent();
    }
}
