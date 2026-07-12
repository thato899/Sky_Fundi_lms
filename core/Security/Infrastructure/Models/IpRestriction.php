<?php

declare(strict_types=1);

namespace Core\Security\Infrastructure\Models;

use Core\Security\Domain\Enums\IpRestrictionType;
use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

final class IpRestriction extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'ip_restrictions';

    protected $fillable = ['scope_type', 'scope_id', 'type', 'ip_cidr', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'type' => IpRestrictionType::class,
            'is_active' => 'boolean',
        ];
    }
}
