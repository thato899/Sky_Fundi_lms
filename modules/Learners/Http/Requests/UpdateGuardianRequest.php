<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('guardian')) ?? false;
    }

    public function rules(): array
    {
        return array_map(static fn (array $rules): array => array_values(array_filter($rules, static fn (mixed $rule): bool => $rule !== 'required')), StoreGuardianRequest::profileRules());
    }
}
