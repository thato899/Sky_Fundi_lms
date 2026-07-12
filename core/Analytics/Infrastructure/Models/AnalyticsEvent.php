<?php

declare(strict_types=1);

namespace Core\Analytics\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

final class AnalyticsEvent extends Model
{
    use HasUuidPrimaryKey;

    public const UPDATED_AT = null;

    protected $table = 'analytics_events';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:4',
            'metadata' => 'array',
            'recorded_at' => 'datetime',
        ];
    }
}
