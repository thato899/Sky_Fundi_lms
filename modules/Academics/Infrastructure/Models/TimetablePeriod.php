<?php

declare(strict_types=1);

namespace Modules\Academics\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Modules\Academics\Domain\Enums\AcademicStatus;
use Modules\Academics\Domain\Enums\DayOfWeek;
use Modules\Academics\Infrastructure\Concerns\BelongsToOrganization;

/**
 * @property bool $is_break
 * @property AcademicStatus $status
 * @property string $start_time
 * @property string $end_time
 */
final class TimetablePeriod extends Model
{
    use BelongsToOrganization;
    use HasUuidPrimaryKey;

    protected $table = 'academics_timetable_periods';

    protected $fillable = ['organization_id', 'name', 'code', 'day_of_week', 'start_time', 'end_time', 'is_break', 'order', 'status'];

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
