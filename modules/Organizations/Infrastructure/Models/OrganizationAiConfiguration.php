<?php

declare(strict_types=1);

namespace Modules\Organizations\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

final class OrganizationAiConfiguration extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['organization_id', 'provider', 'credentials', 'configuration'];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return ['credentials' => 'encrypted:array', 'configuration' => 'array'];
    }
}
