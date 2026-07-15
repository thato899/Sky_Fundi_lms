<?php

declare(strict_types=1);

namespace Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Attendance\Domain\Enums\AttendanceSessionType;

final class UpdateAttendanceSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('session')) ?? false;
    }

    public function rules(): array
    {
        return ['academic_year_id' => ['sometimes', 'uuid'], 'academic_term_id' => ['nullable', 'uuid'], 'class_id' => ['sometimes', 'uuid'], 'subject_id' => ['nullable', 'uuid'], 'timetable_period_id' => ['nullable', 'uuid'], 'staff_profile_id' => ['nullable', 'uuid'], 'session_date' => ['sometimes', 'date'], 'start_time' => ['nullable', 'date_format:H:i'], 'end_time' => ['nullable', 'date_format:H:i'], 'session_type' => ['sometimes', Rule::enum(AttendanceSessionType::class)], 'title' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:2000']];
    }
}
