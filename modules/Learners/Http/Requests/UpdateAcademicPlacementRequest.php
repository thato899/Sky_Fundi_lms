<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAcademicPlacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageAcademicProfile', $this->route('learner')) ?? false;
    }

    public function rules(): array
    {
        return [
            'current_academic_year_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('academics_academic_years', 'id')->where('organization_id', $this->attributes->get('organization')?->getKey())],
            'current_grade_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('academics_grades', 'id')->where('organization_id', $this->attributes->get('organization')?->getKey())],
            'current_class_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('academics_classes', 'id')->where('organization_id', $this->attributes->get('organization')?->getKey())],
            'curriculum_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('academics_curricula', 'id')->where('organization_id', $this->attributes->get('organization')?->getKey())],
            'organization_id' => ['prohibited'],
        ];
    }
}
