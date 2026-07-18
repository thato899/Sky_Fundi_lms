<?php

declare(strict_types=1);

namespace Modules\Assessments\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $uuid
 * @property string $status
 * @property string|null $score_percentage
 * @property array<string, mixed>|null $evaluation
 */
final class QuizRevisionAttempt extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['uuid', 'organization_id', 'quiz_study_plan_id', 'learner_profile_id', 'attempt_number', 'responses', 'evaluation', 'score_percentage', 'status', 'submitted_at', 'evaluated_at'];

    public function uniqueIds(): array
    {
        return ['id', 'uuid'];
    }

    protected function casts(): array
    {
        return ['attempt_number' => 'integer', 'responses' => 'array', 'evaluation' => 'array', 'score_percentage' => 'decimal:2', 'submitted_at' => 'datetime', 'evaluated_at' => 'datetime'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(QuizStudyPlan::class, 'quiz_study_plan_id');
    }
}
