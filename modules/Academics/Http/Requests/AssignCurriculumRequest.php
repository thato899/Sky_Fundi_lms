<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssignCurriculumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('academics.curriculum.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'curriculum_id' => ['required', 'uuid', Rule::exists('academics_curricula', 'id')->where('organization_id', $this->attributes->get('organization')?->getKey())],
        ];
    }
}
