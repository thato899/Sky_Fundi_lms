<?php

declare(strict_types=1);

namespace Modules\Academics\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Academics\Domain\Enums\AcademicStatus;
use Modules\Academics\Infrastructure\Concerns\BelongsToOrganization;

/**
 * Named ClassGroup, not Class, because `Class` is a reserved word in
 * PHP — see the migration's own docblock. The database table and API
 * surface still say "class" throughout.
 */
final class ClassGroup extends Model
{
    use BelongsToOrganization;
    use HasUuidPrimaryKey;
    use SoftDeletes;

    protected $table = 'academics_classes';

    protected $attributes = ['status' => 'active'];

    protected $fillable = ['organization_id', 'name', 'capacity', 'academic_year_id', 'grade_id', 'is_homeroom', 'status'];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_homeroom' => 'boolean',
            'status' => AcademicStatus::class,
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    protected function organizationReferences(): array
    {
        return ['academic_year_id' => AcademicYear::class, 'grade_id' => Grade::class];
    }
}
