<?php

declare(strict_types=1);

namespace Modules\Reports\Infrastructure\Models;

use Carbon\Carbon;
use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Academics\Infrastructure\Models\AcademicTerm;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Reports\Domain\Enums\ReportCardStatus;

/**
 * @property string $id
 * @property string $uuid
 * @property string $organization_id
 * @property string $learner_profile_id
 * @property string $reporting_period_id
 * @property int $version_number
 * @property ReportCardStatus $status
 * @property string|null $withdrawal_reason
 * @property Carbon $generated_at
 * @property Carbon|null $published_at
 * @property LearnerProfile $learner
 * @property ReportingPeriod $period
 * @property ReportCardTemplate $template
 * @property GradingScale $gradingScale
 */
final class ReportCard extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['uuid', 'organization_id', 'learner_profile_id', 'academic_year_id', 'academic_term_id', 'reporting_period_id', 'report_card_template_id', 'class_id', 'grade_id', 'grading_scale_id', 'version_number', 'status', 'generated_at', 'generated_by', 'reviewed_at', 'reviewed_by', 'approved_at', 'approved_by', 'published_at', 'published_by', 'withdrawn_at', 'withdrawn_by', 'withdrawal_reason', 'overall_average', 'attendance_session_count', 'present_count', 'absent_count', 'late_count', 'excused_count', 'remote_count', 'overall_comment', 'snapshot_metadata'];

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
        return ['status' => ReportCardStatus::class, 'version_number' => 'integer', 'overall_average' => 'decimal:2', 'generated_at' => 'datetime', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime', 'published_at' => 'datetime', 'withdrawn_at' => 'datetime', 'snapshot_metadata' => 'array'];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class, 'learner_profile_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(ReportingPeriod::class, 'reporting_period_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ReportCardTemplate::class, 'report_card_template_id');
    }

    public function gradingScale(): BelongsTo
    {
        return $this->belongsTo(GradingScale::class);
    }

    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class, 'class_id');
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(ReportCardSubjectResult::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ReportCardComment::class);
    }
}
