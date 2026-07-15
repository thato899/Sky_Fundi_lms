<?php

declare(strict_types=1);

namespace Modules\Assessments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Assessments\Infrastructure\Models\Assessment;

final class AssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Assessment $a */ $a = $this->resource;
        $relation = fn (string $name) => $a->relationLoaded($name) ? $a->getRelation($name) : null;
        $results = $relation('results');

        return ['uuid' => $a->uuid, 'title' => $a->title, 'description' => $a->description, 'assessment_date' => $a->assessment_date?->toDateString(), 'due_date' => $a->due_date?->toDateString(), 'maximum_mark' => $a->maximum_mark, 'weighting' => $a->weighting, 'status' => $a->status->value, 'result_release_status' => $a->result_release_status->value, 'finalized_at' => $a->finalized_at?->toIso8601String(), 'released_at' => $a->released_at?->toIso8601String(), 'academic_year' => $this->item($relation('academicYear')), 'academic_term' => $this->item($relation('academicTerm')), 'grade' => $this->item($relation('grade')), 'class' => $this->item($relation('classGroup')), 'subject' => $this->item($relation('subject')), 'category' => $this->item($relation('category')), 'results' => $results?->map(function ($result): array {
            $learner = $result->getRelation('learner');

            return ['uuid' => $result->uuid, 'learner_uuid' => $learner->uuid, 'learner_number' => $learner->learner_number, 'learner_name' => trim($learner->first_name.' '.$learner->last_name), 'result_status' => $result->result_status->value, 'score' => $result->score, 'percentage' => $result->percentage, 'feedback' => $result->feedback];
        })];
    }

    private function item(mixed $model): ?array
    {
        return $model ? ['id' => $model->getKey(), 'uuid' => $model->getAttribute('uuid'), 'name' => $model->getAttribute('name')] : null;
    }
}
