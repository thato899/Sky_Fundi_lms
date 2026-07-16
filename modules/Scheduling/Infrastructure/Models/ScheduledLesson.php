<?php

declare(strict_types=1);

namespace Modules\Scheduling\Infrastructure\Models;

use Carbon\CarbonImmutable;
use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Scheduling\Domain\Enums\DeliveryMode;
use Modules\Scheduling\Domain\Enums\LessonStatus;
use Modules\Staff\Infrastructure\Models\StaffProfile;

/**
 * @property string $organization_id
 * @property string $academic_year_id
 * @property string|null $academic_term_id
 * @property string $grade_id
 * @property string $class_id
 * @property string $subject_id
 * @property string|null $room_id
 * @property CarbonImmutable $lesson_date
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property LessonStatus $status
 * @property string|null $title
 * @property Collection<int, StaffProfile> $staff
 */
final class ScheduledLesson extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['uuid', 'organization_id', 'academic_year_id', 'academic_term_id', 'timetable_template_entry_id', 'grade_id', 'class_id', 'subject_id', 'room_id', 'lesson_date', 'starts_at', 'ends_at', 'delivery_mode', 'status', 'title', 'lesson_objective', 'lesson_notes', 'cancellation_reason', 'rescheduled_from_lesson_id', 'created_by', 'updated_by'];

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
        return ['lesson_date' => 'date', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'delivery_mode' => DeliveryMode::class, 'status' => LessonStatus::class];
    }

    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class, 'class_id');
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(StaffProfile::class, 'scheduled_lesson_staff')->withPivot(['assignment_type', 'is_primary'])->withTimestamps();
    }

    public function changes(): HasMany
    {
        return $this->hasMany(ScheduleChangeLog::class);
    }
}
