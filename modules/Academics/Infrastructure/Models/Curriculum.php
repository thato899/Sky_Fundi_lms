<?php

declare(strict_types=1);

namespace Modules\Academics\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Curriculum extends Model
{
    use HasUuidPrimaryKey;
    use SoftDeletes;

    protected $table = 'academics_curricula';

    protected $fillable = ['name', 'code', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }
}
