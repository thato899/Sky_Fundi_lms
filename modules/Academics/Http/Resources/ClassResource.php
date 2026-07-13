<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Modules\Academics\Infrastructure\Models\ClassGroup */
final class ClassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'capacity' => $this->capacity,
            'academic_year_id' => $this->academic_year_id,
            'grade_id' => $this->grade_id,
            'is_homeroom' => $this->is_homeroom,
            'status' => $this->status->value,
        ];
    }
}
