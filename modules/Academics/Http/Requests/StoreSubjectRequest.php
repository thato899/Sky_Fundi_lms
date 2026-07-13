<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('academics.subjects.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:academics_subjects,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'curriculum_id' => ['nullable', 'uuid', 'exists:academics_curricula,id'],
            'department_id' => ['nullable', 'uuid', 'exists:academics_departments,id'],
            'colour' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }
}
