<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Modules\Academics\Infrastructure\Models\TimetablePeriod */
final class TimetablePeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'day_of_week' => $this->day_of_week->value,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'is_break' => $this->is_break,
            'order' => $this->order,
            'status' => $this->status->value,
        ];
    }
}
