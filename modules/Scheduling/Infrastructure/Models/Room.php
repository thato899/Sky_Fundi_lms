<?php

declare(strict_types=1);

namespace Modules\Scheduling\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $organization_id
 * @property string $location_type
 * @property bool $is_active
 */
final class Room extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'scheduling_rooms';

    protected $fillable = ['uuid', 'organization_id', 'name', 'code', 'location_type', 'capacity', 'description', 'is_active', 'online_url', 'created_by', 'updated_by'];

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
        return ['capacity' => 'integer', 'is_active' => 'boolean'];
    }
}
