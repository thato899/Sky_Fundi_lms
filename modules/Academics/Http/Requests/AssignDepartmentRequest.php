<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssignDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('academics.subjects.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'department_id' => ['required', 'uuid', Rule::exists('academics_departments', 'id')->where('organization_id', $this->attributes->get('organization')?->getKey())],
        ];
    }
}
