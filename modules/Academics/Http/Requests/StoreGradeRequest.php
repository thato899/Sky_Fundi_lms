<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('academics.grades.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'order' => ['required', 'integer', 'min:1'],
            'curriculum_id' => ['nullable', 'uuid', 'exists:academics_curricula,id'],
            'academic_year_id' => ['nullable', 'uuid', 'exists:academics_academic_years,id'],
        ];
    }
}
