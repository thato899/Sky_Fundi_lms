<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuardianRelationshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageGuardians', $this->route('learner')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['is_primary', 'is_emergency_contact', 'is_authorized_pickup', 'receives_academic_communication', 'receives_financial_communication'] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'guardian_uuid' => ['required', 'uuid'],
            'relationship_type' => ['required', Rule::in(['parent', 'legal_guardian', 'caregiver', 'other'])],
            'is_primary' => ['sometimes', 'boolean'],
            'is_emergency_contact' => ['sometimes', 'boolean'],
            'is_authorized_pickup' => ['sometimes', 'boolean'],
            'receives_academic_communication' => ['sometimes', 'boolean'],
            'receives_financial_communication' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }
}
