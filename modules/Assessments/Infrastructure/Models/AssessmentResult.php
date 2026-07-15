<?php

declare(strict_types=1);

namespace Modules\Assessments\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Assessments\Database\Factories\AssessmentResultFactory;
use Modules\Assessments\Domain\Enums\AssessmentResultStatus;
use Modules\Learners\Infrastructure\Models\LearnerProfile;

/**
 * @property string $uuid
 * @property string|null $score
 * @property string|null $percentage
 * @property AssessmentResultStatus $result_status
 * @property string|null $feedback
 * @property LearnerProfile $learner
 */
final class AssessmentResult extends Model
{
    use HasFactory;
    use HasUuidPrimaryKey;

    protected $fillable = ['uuid', 'organization_id', 'assessment_id', 'learner_profile_id', 'score', 'percentage', 'result_status', 'feedback', 'private_note', 'marked_by', 'marked_at', 'updated_by'];

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
        return ['score' => 'decimal:2', 'percentage' => 'decimal:2', 'result_status' => AssessmentResultStatus::class, 'marked_at' => 'datetime'];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class, 'learner_profile_id');
    }

    protected static function newFactory(): AssessmentResultFactory
    {
        return AssessmentResultFactory::new();
    }
}
