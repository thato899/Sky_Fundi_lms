<?php

declare(strict_types=1);

namespace Modules\Assessments\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $uuid
 * @property string $organization_id
 * @property string $quiz_attempt_id
 * @property string $learner_profile_id
 * @property array<string, mixed> $content
 * @property int $version
 * @property string $status
 * @property int $completion_percentage
 * @property int $time_spent_minutes
 * @property array<int, string>|null $completed_activities
 * @property array<int, string>|null $mastered_concepts
 * @property array<int, string>|null $remaining_concepts
 * @property mixed $last_activity_at
 * @property mixed $completed_at
 * @property QuizAttempt $attempt
 * @property Collection<int, QuizRevisionAttempt> $revisionAttempts
 */
final class QuizStudyPlan extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['uuid', 'organization_id', 'quiz_attempt_id', 'learner_profile_id', 'version', 'content', 'provider', 'model', 'status', 'approved_by', 'approved_at', 'completion_percentage', 'time_spent_minutes', 'completed_activities', 'mastered_concepts', 'remaining_concepts', 'last_activity_at', 'completed_at', 'published_at', 'published_by'];

    public function uniqueIds(): array
    {
        return ['id', 'uuid'];
    }

    protected function casts(): array
    {
        return ['version' => 'integer', 'content' => 'array', 'approved_at' => 'datetime', 'completion_percentage' => 'integer', 'time_spent_minutes' => 'integer', 'completed_activities' => 'array', 'mastered_concepts' => 'array', 'remaining_concepts' => 'array', 'last_activity_at' => 'datetime', 'completed_at' => 'datetime', 'published_at' => 'datetime'];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    public function revisionAttempts(): HasMany
    {
        return $this->hasMany(QuizRevisionAttempt::class);
    }
}
