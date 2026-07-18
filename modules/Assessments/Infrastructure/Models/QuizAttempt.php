<?php

declare(strict_types=1);

namespace Modules\Assessments\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Learners\Infrastructure\Models\LearnerProfile;

/**
 * @property string $uuid
 * @property string $organization_id
 * @property string $learner_profile_id
 * @property string $status
 * @property string|null $reviewed_by
 * @property string|null $released_by
 * @property string|null $final_score
 * @property mixed $reviewed_at
 * @property mixed $released_at
 * @property Assessment $assessment
 * @property LearnerProfile $learner
 * @property Collection<int, QuizAnswer> $answers
 * @property QuizStudyPlan|null $publishedStudyPlan
 */
final class QuizAttempt extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['uuid', 'organization_id', 'assessment_id', 'assessment_result_id', 'learner_profile_id', 'attempt_number', 'status', 'started_at', 'submitted_at', 'reviewed_at', 'reviewed_by', 'released_at', 'released_by', 'final_score'];

    public function uniqueIds(): array
    {
        return ['id', 'uuid'];
    }

    protected function casts(): array
    {
        return ['attempt_number' => 'integer', 'started_at' => 'datetime', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'released_at' => 'datetime', 'final_score' => 'decimal:2'];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(AssessmentResult::class, 'assessment_result_id');
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class, 'learner_profile_id');
    }

    /** @return HasMany<QuizAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class);
    }

    public function studyPlan(): HasOne
    {
        return $this->hasOne(QuizStudyPlan::class)->latestOfMany('version');
    }

    public function publishedStudyPlan(): HasOne
    {
        return $this->hasOne(QuizStudyPlan::class)->ofMany(['version' => 'max'], fn ($query) => $query->where('status', 'published'));
    }
}
