<?php

declare(strict_types=1);

namespace Core\FeatureFlags\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FeatureFlag extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'feature_flags';

    protected $fillable = ['key', 'name', 'description', 'is_enabled_globally'];

    protected function casts(): array
    {
        return ['is_enabled_globally' => 'boolean'];
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(FeatureFlagOverride::class);
    }
}
