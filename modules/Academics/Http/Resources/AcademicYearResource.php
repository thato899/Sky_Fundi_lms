<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Modules\Academics\Infrastructure\Models\AcademicYear */
final class AcademicYearResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'status' => $this->status->value,
            'is_current' => $this->is_current,
            'terms' => AcademicTermResource::collection($this->whenLoaded('terms')),
        ];
    }
}
