<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Learners\Infrastructure\Models\LearnerEnrolment;

final class LearnerEnrolmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $enrolment = $this->resource;
        assert($enrolment instanceof LearnerEnrolment);

        return [
            'id' => $enrolment->getKey(),
            'academic_year_id' => $enrolment->getAttribute('academic_year_id'),
            'academic_year_name' => $this->relatedName($enrolment, 'academicYear'),
            'grade_id' => $enrolment->getAttribute('grade_id'),
            'grade_name' => $this->relatedName($enrolment, 'grade'),
            'class_id' => $enrolment->getAttribute('class_id'),
            'class_name' => $this->relatedName($enrolment, 'classGroup'),
            'curriculum_id' => $enrolment->getAttribute('curriculum_id'),
            'curriculum_name' => $this->relatedName($enrolment, 'curriculum'),
            'started_on' => $enrolment->getAttribute('started_on')?->toDateString(),
            'ended_on' => $enrolment->getAttribute('ended_on')?->toDateString(),
            'is_open' => $enrolment->getAttribute('ended_on') === null,
            'actor' => $this->when($enrolment->relationLoaded('actor'), function () use ($enrolment): ?array {
                $actor = $enrolment->getRelation('actor');

                return $actor instanceof Model ? ['id' => $actor->getKey(), 'name' => $actor->getAttribute('name')] : null;
            }),
        ];
    }

    private function relatedName(LearnerEnrolment $enrolment, string $relation): ?string
    {
        if (! $enrolment->relationLoaded($relation)) {
            return null;
        }
        $related = $enrolment->getRelation($relation);

        return $related instanceof Model ? $related->getAttribute('name') : null;
    }
}
