<?php

declare(strict_types=1);

namespace Modules\Assessments\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Academics\Infrastructure\Models\AcademicTerm;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Assessments\Database\Factories\AssessmentFactory;
use Modules\Assessments\Domain\Enums\AssessmentStatus;
use Modules\Assessments\Domain\Enums\ResultReleaseStatus;
use Modules\Staff\Infrastructure\Models\StaffProfile;

/**
 * @property string $uuid
 * @property string $organization_id
 * @property string $title
 * @property string|null $description
 * @property mixed $assessment_date
 * @property mixed $due_date
 * @property string $maximum_mark
 * @property string|null $weighting
 * @property AssessmentStatus $status
 * @property ResultReleaseStatus $result_release_status
 * @property mixed $finalized_at
 * @property mixed $released_at
 * @property Collection<int, AssessmentResult> $results
 */
final class Assessment extends Model
{
    use HasFactory;
    use HasUuidPrimaryKey;

    protected $fillable = ['uuid', 'organization_id', 'academic_year_id', 'academic_term_id', 'grade_id', 'class_id', 'subject_id', 'assessment_category_id', 'staff_profile_id', 'title', 'description', 'assessment_date', 'due_date', 'maximum_mark', 'weighting', 'status', 'result_release_status', 'instructions', 'finalized_at', 'finalized_by', 'reopened_at', 'reopened_by', 'reopen_reason', 'released_at', 'released_by', 'created_by', 'updated_by'];

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
        return ['assessment_date' => 'date', 'due_date' => 'date', 'maximum_mark' => 'decimal:2', 'weighting' => 'decimal:4', 'status' => AssessmentStatus::class, 'result_release_status' => ResultReleaseStatus::class, 'finalized_at' => 'datetime', 'reopened_at' => 'datetime', 'released_at' => 'datetime'];
    }

    public function results(): HasMany
    {
        return $this->hasMany(AssessmentResult::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssessmentCategory::class, 'assessment_category_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    protected static function newFactory(): AssessmentFactory
    {
        return AssessmentFactory::new();
    }
}
