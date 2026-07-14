<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateAcademicPlacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageAcademicProfile', $this->route('learner')) ?? false;
    }

    public function rules(): array
    {
        return [
            'current_academic_year_id' => ['sometimes', 'nullable', 'uuid', 'exists:academics_academic_years,id'],
            'current_grade_id' => ['sometimes', 'nullable', 'uuid', 'exists:academics_grades,id'],
            'current_class_id' => ['sometimes', 'nullable', 'uuid', 'exists:academics_classes,id'],
            'curriculum_id' => ['sometimes', 'nullable', 'uuid', 'exists:academics_curricula,id'],
            'organization_id' => ['prohibited'],
        ];
    }
}
