<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateTimetablePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('academics.timetable.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i', 'after:start_time'],
            'is_break' => ['sometimes', 'boolean'],
            'order' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', 'string', 'in:active,inactive,archived'],
        ];
    }
}
