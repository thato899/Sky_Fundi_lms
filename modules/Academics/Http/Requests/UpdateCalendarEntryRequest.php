<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateCalendarEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('academics.calendar.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', 'in:school_day,public_holiday,exam_period,assessment_period,event'],
            'name' => ['sometimes', 'string', 'max:255'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
