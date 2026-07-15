<?php

declare(strict_types=1);

namespace Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Attendance\Domain\Enums\AttendanceStatus;

final class RecordAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('record', $this->route('session')) ?? false;
    }

    public function rules(): array
    {
        return ['entries' => ['required', 'array'], 'entries.*.entry_uuid' => ['required', 'uuid', 'distinct'], 'entries.*.status' => ['required', Rule::enum(AttendanceStatus::class)], 'entries.*.arrival_time' => ['nullable', 'date_format:H:i'], 'entries.*.departure_time' => ['nullable', 'date_format:H:i'], 'entries.*.reason' => ['nullable', 'string', 'max:500'], 'entries.*.note' => ['nullable', 'string', 'max:1000']];
    }
}
