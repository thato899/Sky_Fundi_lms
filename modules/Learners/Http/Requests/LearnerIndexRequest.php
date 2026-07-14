<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Learners\Domain\Enums\LearnerStatus;
use Modules\Learners\Infrastructure\Models\LearnerProfile;

final class LearnerIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', LearnerProfile::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['portal_access_enabled', 'archived'] as $field) {
            if ($this->has($field)) {
                $value = filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($value !== null) {
                    $this->merge([$field => $value]);
                }
            }
        }
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'learner_status' => ['nullable', Rule::enum(LearnerStatus::class)],
            'onboarding_status' => ['nullable', Rule::in(['pending', 'in_progress', 'completed'])],
            'academic_year_id' => ['nullable', 'uuid'],
            'curriculum_id' => ['nullable', 'uuid'],
            'grade_id' => ['nullable', 'uuid'],
            'class_id' => ['nullable', 'uuid'],
            'portal_access_enabled' => ['nullable', 'boolean'],
            'archived' => ['nullable', 'boolean'],
            'admission_date_from' => ['nullable', 'date'],
            'admission_date_to' => ['nullable', 'date', 'after_or_equal:admission_date_from'],
            'sort' => ['nullable', Rule::in(['learner_number', 'first_name', 'last_name', 'admission_date', 'learner_status', 'created_date'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
