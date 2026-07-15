<?php

declare(strict_types=1);

namespace Modules\Academics\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Modules\Academics\Domain\Enums\AcademicStatus;
use Modules\Academics\Domain\Enums\DayOfWeek;
use Modules\Academics\Infrastructure\Concerns\BelongsToOrganization;

final class TimetablePeriod extends Model
{
    use BelongsToOrganization;
    use HasUuidPrimaryKey;

    protected $table = 'academics_timetable_periods';

    protected $fillable = ['organization_id', 'name', 'day_of_week', 'start_time', 'end_time', 'is_break', 'order', 'status'];

    protected function casts(): array
    {
        return [
            'day_of_week' => DayOfWeek::class,
            'is_break' => 'boolean',
            'order' => 'integer',
            'status' => AcademicStatus::class,
        ];
    }
}
