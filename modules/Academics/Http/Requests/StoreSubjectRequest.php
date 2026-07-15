<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('academics.subjects.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('academics_subjects', 'code')->where('organization_id', $this->attributes->get('organization')?->getKey())],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'curriculum_id' => ['nullable', 'uuid', Rule::exists('academics_curricula', 'id')->where('organization_id', $this->attributes->get('organization')?->getKey())],
            'department_id' => ['nullable', 'uuid', Rule::exists('academics_departments', 'id')->where('organization_id', $this->attributes->get('organization')?->getKey())],
            'colour' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'organization_id' => ['prohibited'],
        ];
    }
}
