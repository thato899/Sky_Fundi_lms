<?php

declare(strict_types=1);

namespace Modules\Assessments\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

/** @property string $status */
final class AiGradingRequest extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['uuid', 'organization_id', 'quiz_attempt_id', 'quiz_answer_id', 'request_type', 'idempotency_key', 'provider', 'model', 'status', 'input_tokens', 'output_tokens', 'estimated_cost', 'failure_message', 'completed_at'];

    public function uniqueIds(): array
    {
        return ['id', 'uuid'];
    }

    protected function casts(): array
    {
        return ['input_tokens' => 'integer', 'output_tokens' => 'integer', 'estimated_cost' => 'decimal:6', 'completed_at' => 'datetime'];
    }
}
