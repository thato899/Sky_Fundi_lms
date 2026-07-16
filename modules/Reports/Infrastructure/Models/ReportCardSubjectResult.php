<?php

declare(strict_types=1);

namespace Modules\Reports\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Reports\Domain\Enums\SubjectResultStatus;

/**
 * @property string $grading_band_label
 * @property SubjectResultStatus $subject_result_status
 */
final class ReportCardSubjectResult extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['uuid', 'organization_id', 'report_card_id', 'subject_id', 'subject_name_snapshot', 'subject_code_snapshot', 'marked_assessment_count', 'total_valid_weighting', 'calculated_percentage', 'grading_band_label', 'grading_band_symbol', 'subject_result_status', 'teacher_comment', 'display_order'];

    public function uniqueIds(): array
    {
        return ['id', 'uuid'];
    }

    protected function casts(): array
    {
        return ['marked_assessment_count' => 'integer', 'total_valid_weighting' => 'decimal:2', 'calculated_percentage' => 'decimal:2', 'subject_result_status' => SubjectResultStatus::class, 'display_order' => 'integer'];
    }

    public function reportCard(): BelongsTo
    {
        return $this->belongsTo(ReportCard::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
