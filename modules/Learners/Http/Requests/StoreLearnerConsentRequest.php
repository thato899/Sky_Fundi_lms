<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreLearnerConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageGuardians', $this->route('learner')) ?? false;
    }

    public function rules(): array
    {
        return [
            'guardian_uuid' => ['nullable', 'uuid'],
            'consent_type' => ['required', 'string', 'max:100'],
            'status' => ['required', Rule::in(['granted', 'declined', 'withdrawn', 'expired'])],
            'recorded_date' => ['required', 'date', 'before_or_equal:today'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:recorded_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
