<?php

declare(strict_types=1);

namespace Modules\Assessments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Assessments\Infrastructure\Models\Assessment;

final class StoreAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Assessment::class) ?? false;
    }

    public function rules(): array
    {
        return ['organization_id' => ['prohibited'], 'academic_year_id' => ['required', 'uuid'], 'academic_term_id' => ['required', 'uuid'], 'grade_id' => ['required', 'uuid'], 'class_id' => ['required', 'uuid'], 'subject_id' => ['required', 'uuid'], 'assessment_category_id' => ['required', 'uuid'], 'staff_profile_id' => ['nullable', 'uuid'], 'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'], 'assessment_date' => ['nullable', 'date'], 'due_date' => ['nullable', 'date', 'after_or_equal:assessment_date'], 'maximum_mark' => ['required', 'numeric', 'gt:0', 'max:99999999.99'], 'weighting' => ['nullable', 'numeric', 'between:0,100'], 'instructions' => ['nullable', 'string', 'max:5000'], 'opens_at' => ['nullable', 'date'], 'closes_at' => ['nullable', 'date', 'after:opens_at'], 'time_limit_minutes' => ['nullable', 'integer', 'between:1,600'], 'attempt_limit' => ['nullable', 'integer', 'between:1,10']];
    }
}
