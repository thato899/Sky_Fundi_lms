<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreAcademicTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('academics.terms.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'term_number' => ['required', 'integer', 'min:1', 'max:12'],
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ];
    }
}
