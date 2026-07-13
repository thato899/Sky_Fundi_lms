<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AssignDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('academics.subjects.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'department_id' => ['required', 'uuid', 'exists:academics_departments,id'],
        ];
    }
}
