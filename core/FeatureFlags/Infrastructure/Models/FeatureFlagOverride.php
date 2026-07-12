<?php

declare(strict_types=1);

namespace Core\FeatureFlags\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FeatureFlagOverride extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'feature_flag_overrides';

    protected $fillable = ['feature_flag_id', 'scope_type', 'scope_id', 'is_enabled'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    public function featureFlag(): BelongsTo
    {
        return $this->belongsTo(FeatureFlag::class);
    }
}
