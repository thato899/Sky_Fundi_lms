<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('academics.classes.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'is_homeroom' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', 'in:active,inactive,archived'],
        ];
    }
}
