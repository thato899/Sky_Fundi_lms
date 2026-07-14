<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Resources;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Learners\Domain\Enums\LearnerStatus;
use Modules\Learners\Infrastructure\Models\LearnerProfile;

final class LearnerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $learner = $this->resource;
        assert($learner instanceof LearnerProfile);
        $status = $learner->getAttribute('learner_status');
        assert($status instanceof LearnerStatus);

        return [
            'uuid' => $learner->getAttribute('uuid'),
            'learner_number' => $learner->getAttribute('learner_number'),
            'admission_number' => $learner->getAttribute('admission_number'),
            'first_name' => $learner->getAttribute('first_name'),
            'middle_name' => $learner->getAttribute('middle_name'),
            'last_name' => $learner->getAttribute('last_name'),
            'preferred_name' => $learner->getAttribute('preferred_name'),
            'date_of_birth' => $this->date($learner->getAttribute('date_of_birth')),
            'learner_email' => $learner->getAttribute('learner_email'),
            'learner_phone' => $learner->getAttribute('learner_phone'),
            'residential_address' => $learner->getAttribute('residential_address'),
            'city' => $learner->getAttribute('city'),
            'province' => $learner->getAttribute('province'),
            'country' => $learner->getAttribute('country'),
            'postal_code' => $learner->getAttribute('postal_code'),
            'admission_date' => $this->date($learner->getAttribute('admission_date')),
            'expected_completion_date' => $this->date($learner->getAttribute('expected_completion_date')),
            'previous_institution' => $learner->getAttribute('previous_institution'),
            'language_of_instruction' => $learner->getAttribute('language_of_instruction'),
            'home_language' => $learner->getAttribute('home_language'),
            'learning_mode' => $learner->getAttribute('learning_mode'),
            'academic_placement' => [
                'academic_year' => $this->when($learner->relationLoaded('currentAcademicYear'), fn () => $this->summary($learner->getRelation('currentAcademicYear'))),
                'grade' => $this->when($learner->relationLoaded('currentGrade'), fn () => $this->summary($learner->getRelation('currentGrade'))),
                'class' => $this->when($learner->relationLoaded('currentClass'), fn () => $this->summary($learner->getRelation('currentClass'))),
                'curriculum' => $this->when($learner->relationLoaded('curriculum'), fn () => $this->summary($learner->getRelation('curriculum'))),
            ],
            'learner_status' => $status->value,
            'academic_status' => $learner->getAttribute('academic_status'),
            'onboarding_status' => $learner->getAttribute('onboarding_status'),
            'portal_access_enabled' => $learner->getAttribute('portal_access_enabled'),
            'archived' => $learner->getAttribute('archived_at') !== null,
            'archived_at' => $this->timestamp($learner->getAttribute('archived_at')),
            'created_at' => $this->timestamp($learner->getAttribute('created_at')),
            'updated_at' => $this->timestamp($learner->getAttribute('updated_at')),
        ];
    }

    private function summary(mixed $model): ?array
    {
        if (! $model instanceof Model) {
            return null;
        }

        return array_filter([
            'id' => $model->getKey(),
            'name' => $model->getAttribute('name'),
            'code' => $model->getAttribute('code'),
        ], fn (mixed $value): bool => $value !== null);
    }

    private function date(mixed $value): ?string
    {
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : null;
    }

    private function timestamp(mixed $value): ?string
    {
        return $value instanceof DateTimeInterface ? $value->format(DATE_ATOM) : null;
    }
}
