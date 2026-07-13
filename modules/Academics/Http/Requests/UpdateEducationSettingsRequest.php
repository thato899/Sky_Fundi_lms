<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateEducationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('academics.academic-years.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'values' => ['required', 'array'],
        ];
    }
}
