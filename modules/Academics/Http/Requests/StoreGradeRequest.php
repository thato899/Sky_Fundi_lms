<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'curriculum_id' => ['nullable', 'uuid', Rule::exists('academics_curricula', 'id')->where('organization_id', $this->attributes->get('organization')?->getKey())],
            'academic_year_id' => ['nullable', 'uuid', Rule::exists('academics_academic_years', 'id')->where('organization_id', $this->attributes->get('organization')?->getKey())],
            'organization_id' => ['prohibited'],
        ];
    }
}
