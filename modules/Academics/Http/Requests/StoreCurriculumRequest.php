<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCurriculumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('academics.curriculum.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:academics_curricula,code'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
