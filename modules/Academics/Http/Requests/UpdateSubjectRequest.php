<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('academics.subjects.manage') ?? false;
    }

    public function rules(): array
    {
        $subjectId = $this->route('subject')?->id;

        return [
            'code' => ['sometimes', 'string', 'max:50', 'unique:academics_subjects,code,'.$subjectId],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'colour' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'status' => ['sometimes', 'string', 'in:active,inactive,archived'],
        ];
    }
}
