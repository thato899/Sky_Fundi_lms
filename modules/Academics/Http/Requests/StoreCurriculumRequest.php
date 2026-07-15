<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'code' => ['required', 'string', 'max:50', Rule::unique('academics_curricula', 'code')->where('organization_id', $this->attributes->get('organization')?->getKey())],
            'organization_id' => ['prohibited'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
