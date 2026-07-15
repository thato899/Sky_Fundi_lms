<?php

declare(strict_types=1);

namespace Modules\Assessments\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Assessments\Database\Factories\AssessmentCategoryFactory;

final class AssessmentCategory extends Model
{
    use HasFactory;
    use HasUuidPrimaryKey;

    protected $fillable = ['uuid', 'organization_id', 'name', 'code', 'description', 'default_weighting', 'is_active', 'display_order', 'created_by', 'updated_by'];

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
        return ['default_weighting' => 'decimal:4', 'is_active' => 'boolean', 'display_order' => 'integer'];
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    protected static function newFactory(): AssessmentCategoryFactory
    {
        return AssessmentCategoryFactory::new();
    }
}
