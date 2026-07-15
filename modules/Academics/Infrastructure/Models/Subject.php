<?php

declare(strict_types=1);

namespace Modules\Academics\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Academics\Domain\Enums\AcademicStatus;
use Modules\Academics\Infrastructure\Concerns\BelongsToOrganization;

final class Subject extends Model
{
    use BelongsToOrganization;
    use HasUuidPrimaryKey;
    use SoftDeletes;

    protected $table = 'academics_subjects';

    protected $attributes = ['status' => 'active'];

    protected $fillable = [
        'organization_id', 'code', 'name', 'description', 'curriculum_id', 'department_id',
        'colour', 'ai_configuration', 'status',
    ];

    protected function casts(): array
    {
        return [
            'ai_configuration' => 'array',
            'status' => AcademicStatus::class,
        ];
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    protected function organizationReferences(): array
    {
        return ['curriculum_id' => Curriculum::class, 'department_id' => Department::class];
    }
}
