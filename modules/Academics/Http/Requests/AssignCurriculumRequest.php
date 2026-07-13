<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AssignCurriculumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('academics.curriculum.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'curriculum_id' => ['required', 'uuid', 'exists:academics_curricula,id'],
        ];
    }
}
