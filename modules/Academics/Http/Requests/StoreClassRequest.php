<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'academic_year_id' => ['required', 'uuid', Rule::exists('academics_academic_years', 'id')->where('organization_id', $this->attributes->get('organization')?->getKey())],
            'grade_id' => ['required', 'uuid', Rule::exists('academics_grades', 'id')->where('organization_id', $this->attributes->get('organization')?->getKey())],
            'is_homeroom' => ['sometimes', 'boolean'],
            'organization_id' => ['prohibited'],
        ];
    }
}
