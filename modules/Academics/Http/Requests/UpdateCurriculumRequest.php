<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateCurriculumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('academics.curriculum.manage') ?? false;
    }

    public function rules(): array
    {
        $curriculumId = $this->route('curriculum')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:50', 'unique:academics_curricula,code,'.$curriculumId],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
