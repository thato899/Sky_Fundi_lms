<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('academics.grades.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'order' => ['sometimes', 'integer', 'min:1'],
            'academic_year_id' => ['nullable', 'uuid', 'exists:academics_academic_years,id'],
            'status' => ['sometimes', 'string', 'in:active,inactive,archived'],
        ];
    }
}
