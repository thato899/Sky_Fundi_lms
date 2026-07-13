<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreTimetablePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('academics.timetable.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'day_of_week' => ['required', 'string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'is_break' => ['sometimes', 'boolean'],
            'order' => ['required', 'integer', 'min:1'],
        ];
    }
}
