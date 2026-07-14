<?php

declare(strict_types=1);

namespace Modules\Organizations\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

final class OrganizationSetting extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['organization_id', 'group', 'key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }
}
