<?php

declare(strict_types=1);

namespace Modules\Assessments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('assessment')) ?? false;
    }

    public function rules(): array
    {
        return ['organization_id' => ['prohibited'], 'academic_year_id' => ['sometimes', 'uuid'], 'academic_term_id' => ['sometimes', 'uuid'], 'grade_id' => ['sometimes', 'uuid'], 'class_id' => ['sometimes', 'uuid'], 'subject_id' => ['sometimes', 'uuid'], 'assessment_category_id' => ['sometimes', 'uuid'], 'staff_profile_id' => ['nullable', 'uuid'], 'title' => ['sometimes', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'], 'assessment_date' => ['nullable', 'date'], 'due_date' => ['nullable', 'date'], 'maximum_mark' => ['sometimes', 'numeric', 'gt:0'], 'weighting' => ['nullable', 'numeric', 'between:0,100'], 'instructions' => ['nullable', 'string', 'max:5000']];
    }
}
