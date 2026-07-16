<?php

declare(strict_types=1);

namespace Modules\Reports\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $uuid
 * @property string $organization_id
 * @property bool $is_active
 * @property bool $is_default
 * @property Collection<int, GradingScaleBand> $bands
 */
final class GradingScale extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['uuid', 'organization_id', 'name', 'code', 'description', 'pass_threshold', 'is_active', 'is_default', 'created_by', 'updated_by'];

    public function uniqueIds(): array
    {
        return ['id', 'uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return ['pass_threshold' => 'decimal:2', 'is_active' => 'boolean', 'is_default' => 'boolean'];
    }

    public function bands(): HasMany
    {
        return $this->hasMany(GradingScaleBand::class);
    }
}
