<?php

declare(strict_types=1);

namespace Modules\Assessments\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $uuid
 * @property string $organization_id
 * @property string $quiz_attempt_id
 * @property string $marks_available
 * @property string|null $ai_suggested_mark
 * @property string|null $marks_awarded
 * @property string|null $answer_text
 * @property array<string, mixed>|null $ai_feedback
 * @property string|null $teacher_feedback
 * @property mixed $updated_at
 * @property QuizAttempt $attempt
 * @property AssessmentQuestion $question
 */
final class QuizAnswer extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['uuid', 'organization_id', 'quiz_attempt_id', 'assessment_question_id', 'selected_option_id', 'answer_text', 'marks_available', 'ai_suggested_mark', 'marks_awarded', 'marking_method', 'ai_feedback', 'teacher_feedback', 'marked_by', 'marked_at', 'teacher_adjusted'];

    public function uniqueIds(): array
    {
        return ['id', 'uuid'];
    }

    protected function casts(): array
    {
        return ['marks_available' => 'decimal:2', 'ai_suggested_mark' => 'decimal:2', 'marks_awarded' => 'decimal:2', 'ai_feedback' => 'array', 'marked_at' => 'datetime', 'teacher_adjusted' => 'boolean'];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(AssessmentQuestion::class, 'assessment_question_id');
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(AssessmentQuestionOption::class, 'selected_option_id');
    }
}
