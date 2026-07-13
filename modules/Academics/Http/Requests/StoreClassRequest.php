<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('academics.classes.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'academic_year_id' => ['required', 'uuid', 'exists:academics_academic_years,id'],
            'grade_id' => ['required', 'uuid', 'exists:academics_grades,id'],
            'is_homeroom' => ['sometimes', 'boolean'],
        ];
    }
}
