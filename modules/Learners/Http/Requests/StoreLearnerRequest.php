<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Learners\Infrastructure\Models\LearnerProfile;

final class StoreLearnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', LearnerProfile::class) ?? false;
    }

    public function rules(): array
    {
        $organizationId = $this->attributes->get('organization')?->getKey();

        return [
            'learner_number' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/'],
            'admission_number' => ['nullable', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'preferred_name' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'admission_date' => ['nullable', 'date'],
            'expected_completion_date' => ['nullable', 'date', 'after_or_equal:admission_date'],
            'previous_institution' => ['nullable', 'string', 'max:255'],
            'language_of_instruction' => ['nullable', 'string', 'max:100'],
            'home_language' => ['nullable', 'string', 'max:100'],
            'learning_mode' => ['nullable', 'string', 'max:100'],
            'learner_email' => ['nullable', 'email:rfc', 'max:255'],
            'learner_phone' => ['nullable', 'string', 'max:50'],
            'residential_address' => ['nullable', 'string', 'max:2000'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'size:2'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'current_academic_year_id' => ['nullable', 'uuid', Rule::exists('academics_academic_years', 'id')->where('organization_id', $organizationId)],
            'current_grade_id' => ['nullable', 'uuid', Rule::exists('academics_grades', 'id')->where('organization_id', $organizationId)],
            'current_class_id' => ['nullable', 'uuid', Rule::exists('academics_classes', 'id')->where('organization_id', $organizationId)],
            'curriculum_id' => ['nullable', 'uuid', Rule::exists('academics_curricula', 'id')->where('organization_id', $organizationId)],
            'organization_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'organization_membership_id' => ['prohibited'],
            'portal_access_enabled' => ['prohibited'],
        ];
    }
}
