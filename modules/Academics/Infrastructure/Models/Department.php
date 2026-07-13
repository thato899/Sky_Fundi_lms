<?php

declare(strict_types=1);

namespace Modules\Academics\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Department extends Model
{
    use HasUuidPrimaryKey;
    use SoftDeletes;

    protected $table = 'academics_departments';

    protected $fillable = ['name', 'code', 'description', 'colour', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }
}
