<?php

declare(strict_types=1);

namespace Modules\Scheduling\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Scheduling\Domain\Enums\DeliveryMode;

/**
 * @property string $organization_id
 * @property string $class_id
 * @property string|null $room_id
 * @property int $weekday
 * @property string $start_time
 * @property string $end_time
 * @property string $grade_id
 * @property string $subject_id
 * @property string|null $teaching_period_id
 * @property DeliveryMode $delivery_mode
 */
final class TimetableTemplateEntry extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['uuid', 'organization_id', 'timetable_template_id', 'weekday', 'teaching_period_id', 'start_time', 'end_time', 'grade_id', 'class_id', 'subject_id', 'room_id', 'delivery_mode', 'status', 'notes', 'display_order'];

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
        return ['weekday' => 'integer', 'delivery_mode' => DeliveryMode::class, 'display_order' => 'integer'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TimetableTemplate::class, 'timetable_template_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
