<?php

declare(strict_types=1);

namespace Modules\Staff\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TeachingAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'staff_profile_id' => $this->resource->getAttribute('staff_profile_id'),
            'class_id' => $this->resource->getAttribute('class_id'),
            'class_name' => $this->resource->classGroup?->getAttribute('name'),
            'subject_id' => $this->resource->getAttribute('subject_id'),
            'subject_name' => $this->resource->subject?->getAttribute('name'),
            'academic_year_id' => $this->resource->getAttribute('academic_year_id'),
            'academic_year_name' => $this->resource->academicYear?->getAttribute('name'),
            'started_on' => $this->resource->getAttribute('started_on')?->toDateString(),
            'ended_on' => $this->resource->getAttribute('ended_on')?->toDateString(),
            'is_active' => $this->resource->getAttribute('ended_on') === null,
        ];
    }
}
