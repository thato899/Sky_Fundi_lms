<?php

declare(strict_types=1);

namespace Modules\Attendance\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Learners\Infrastructure\Models\LearnerProfile;

final class AttendanceEntry extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['uuid', 'organization_id', 'attendance_session_id', 'learner_profile_id', 'status', 'arrival_time', 'departure_time', 'minutes_late', 'reason', 'note', 'recorded_by', 'updated_by'];

    public function uniqueIds(): array
    {
        return ['id', 'uuid'];
    }

    protected function casts(): array
    {
        return ['status' => AttendanceStatus::class, 'minutes_late' => 'integer'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class, 'learner_profile_id');
    }
}
