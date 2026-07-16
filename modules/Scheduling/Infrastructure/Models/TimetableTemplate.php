<?php

declare(strict_types=1);

namespace Modules\Scheduling\Infrastructure\Models;

use Carbon\CarbonImmutable;
use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Scheduling\Domain\Enums\TemplateStatus;

/**
 * @property string $organization_id
 * @property string $academic_year_id
 * @property string|null $academic_term_id
 * @property TemplateStatus $status
 * @property CarbonImmutable $effective_start_date
 * @property CarbonImmutable $effective_end_date
 * @property Collection<int, TimetableTemplateEntry> $entries
 */
final class TimetableTemplate extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['uuid', 'organization_id', 'academic_year_id', 'academic_term_id', 'name', 'description', 'status', 'effective_start_date', 'effective_end_date', 'is_active', 'created_by', 'updated_by'];

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
        return ['status' => TemplateStatus::class, 'effective_start_date' => 'date', 'effective_end_date' => 'date', 'is_active' => 'boolean'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(TimetableTemplateEntry::class);
    }
}
