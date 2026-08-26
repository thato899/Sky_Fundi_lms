<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CorrectLearnerEnrolmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('correctEnrolment', $this->route('learner')) ?? false;
    }

    public function rules(): array
    {
        $organizationId = $this->attributes->get('organization')?->getKey();

        return [
            'academic_year_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('academics_academic_years', 'id')->where('organization_id', $organizationId)],
            'grade_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('academics_grades', 'id')->where('organization_id', $organizationId)],
            'class_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('academics_classes', 'id')->where('organization_id', $organizationId)],
            'curriculum_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('academics_curricula', 'id')->where('organization_id', $organizationId)],
            'started_on' => ['sometimes', 'date_format:Y-m-d'],
            'ended_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'organization_id' => ['prohibited'],
            'learner_profile_id' => ['prohibited'],
        ];
    }
}
