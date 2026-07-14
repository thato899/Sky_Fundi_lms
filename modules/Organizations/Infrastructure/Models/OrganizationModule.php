<?php

declare(strict_types=1);

namespace Modules\Organizations\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

final class OrganizationModule extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['organization_id', 'module_name', 'enabled', 'enabled_by'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
