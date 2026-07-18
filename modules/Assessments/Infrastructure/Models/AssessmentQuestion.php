<?php

declare(strict_types=1);

namespace Modules\Assessments\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Assessments\Domain\Enums\QuestionType;

/**
 * @property string $uuid
 * @property string $organization_id
 * @property QuestionType $type
 * @property string $prompt
 * @property string $marks_available
 * @property string|null $model_answer
 * @property string|null $marking_guidance
 * @property array<int, string>|null $key_concepts
 * @property Collection<int, AssessmentQuestionOption> $options
 */
final class AssessmentQuestion extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['uuid', 'organization_id', 'assessment_id', 'type', 'prompt', 'marks_available', 'display_order', 'model_answer', 'marking_guidance', 'key_concepts'];

    public function uniqueIds(): array
    {
        return ['id', 'uuid'];
    }

    protected function casts(): array
    {
        return ['type' => QuestionType::class, 'marks_available' => 'decimal:2', 'display_order' => 'integer', 'key_concepts' => 'array'];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /** @return HasMany<AssessmentQuestionOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(AssessmentQuestionOption::class);
    }
}
