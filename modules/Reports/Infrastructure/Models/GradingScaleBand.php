<?php

declare(strict_types=1);

namespace Modules\Reports\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $label
 * @property string|null $symbol
 * @property string $minimum_percentage
 * @property string $maximum_percentage
 */
final class GradingScaleBand extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['uuid', 'organization_id', 'grading_scale_id', 'label', 'code', 'minimum_percentage', 'maximum_percentage', 'symbol', 'description', 'display_order', 'is_passing'];

    public function uniqueIds(): array
    {
        return ['id', 'uuid'];
    }

    protected function casts(): array
    {
        return ['minimum_percentage' => 'decimal:2', 'maximum_percentage' => 'decimal:2', 'display_order' => 'integer', 'is_passing' => 'boolean'];
    }

    public function scale(): BelongsTo
    {
        return $this->belongsTo(GradingScale::class, 'grading_scale_id');
    }
}
