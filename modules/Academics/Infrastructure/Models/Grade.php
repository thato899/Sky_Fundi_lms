<?php

declare(strict_types=1);

namespace Modules\Academics\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Academics\Domain\Enums\AcademicStatus;
use Modules\Academics\Infrastructure\Concerns\BelongsToOrganization;

final class Grade extends Model
{
    use BelongsToOrganization;
    use HasUuidPrimaryKey;
    use SoftDeletes;

    protected $table = 'academics_grades';

    protected $attributes = ['status' => 'active'];

    protected $fillable = ['organization_id', 'name', 'order', 'curriculum_id', 'academic_year_id', 'status'];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'status' => AcademicStatus::class,
        ];
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(ClassGroup::class);
    }

    protected function organizationReferences(): array
    {
        return ['curriculum_id' => Curriculum::class, 'academic_year_id' => AcademicYear::class];
    }
}
