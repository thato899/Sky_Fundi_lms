<?php

declare(strict_types=1);

namespace Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Attendance\Domain\Enums\AttendanceSessionType;
use Modules\Attendance\Infrastructure\Models\AttendanceSession;

final class StoreAttendanceSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AttendanceSession::class) ?? false;
    }

    public function rules(): array
    {
        return ['organization_id' => ['prohibited'], 'academic_year_id' => ['required', 'uuid'], 'academic_term_id' => ['nullable', 'uuid'], 'class_id' => ['required', 'uuid'], 'subject_id' => ['nullable', 'uuid'], 'timetable_period_id' => ['nullable', 'uuid'], 'staff_profile_id' => ['nullable', 'uuid'], 'session_date' => ['required', 'date'], 'start_time' => ['nullable', 'date_format:H:i'], 'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'], 'session_type' => ['required', Rule::enum(AttendanceSessionType::class)], 'title' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:2000']];
    }
}
