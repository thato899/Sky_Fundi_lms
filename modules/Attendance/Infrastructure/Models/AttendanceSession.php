<?php

declare(strict_types=1);

namespace Modules\Attendance\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Academics\Infrastructure\Models\AcademicTerm;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Academics\Infrastructure\Models\TimetablePeriod;
use Modules\Attendance\Domain\Enums\AttendanceSessionStatus;
use Modules\Attendance\Domain\Enums\AttendanceSessionType;
use Modules\Staff\Infrastructure\Models\StaffProfile;

/** @property AttendanceSessionStatus $status */
final class AttendanceSession extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['uuid', 'organization_id', 'scheduled_lesson_id', 'academic_year_id', 'academic_term_id', 'class_id', 'subject_id', 'timetable_period_id', 'staff_profile_id', 'session_date', 'start_time', 'end_time', 'session_type', 'title', 'status', 'notes', 'finalized_at', 'finalized_by', 'reopened_at', 'reopened_by', 'reopen_reason', 'created_by', 'updated_by'];

    public function uniqueIds(): array
    {
        return ['id', 'uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return ['session_date' => 'date', 'session_type' => AttendanceSessionType::class, 'status' => AttendanceSessionStatus::class, 'finalized_at' => 'datetime', 'reopened_at' => 'datetime'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(AttendanceEntry::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function timetablePeriod(): BelongsTo
    {
        return $this->belongsTo(TimetablePeriod::class);
    }

    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }
}
