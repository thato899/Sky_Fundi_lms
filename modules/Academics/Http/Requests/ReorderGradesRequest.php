<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReorderGradesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('academics.grades.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'grade_ids' => ['required', 'array', 'min:1'],
            'grade_ids.*' => ['uuid', 'exists:academics_grades,id'],
        ];
    }
}
