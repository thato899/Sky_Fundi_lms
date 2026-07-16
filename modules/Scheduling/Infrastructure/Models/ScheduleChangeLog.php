<?php

declare(strict_types=1);

namespace Modules\Scheduling\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

final class ScheduleChangeLog extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['organization_id', 'scheduled_lesson_id', 'action', 'before', 'after', 'reason', 'changed_by'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }
}
