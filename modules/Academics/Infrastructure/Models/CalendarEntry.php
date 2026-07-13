<?php

declare(strict_types=1);

namespace Modules\Academics\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academics\Domain\Enums\CalendarEntryType;

final class CalendarEntry extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'academics_calendar_entries';

    protected $fillable = ['academic_year_id', 'type', 'name', 'start_date', 'end_date', 'description'];

    protected function casts(): array
    {
        return [
            'type' => CalendarEntryType::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
