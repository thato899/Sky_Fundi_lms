<?php

declare(strict_types=1);

namespace Modules\Learners\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Organizations\Infrastructure\Models\Organization;

final class LearnerNumberSequence extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['organization_id', 'academic_year', 'prefix', 'padding', 'next_number'];

    protected function casts(): array
    {
        return ['padding' => 'integer', 'next_number' => 'integer'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
