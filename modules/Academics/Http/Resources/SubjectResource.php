<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Academics\Infrastructure\Models\Subject;

/** @mixin Subject */
final class SubjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'curriculum_id' => $this->curriculum_id,
            'department_id' => $this->department_id,
            'colour' => $this->colour,
            'status' => $this->status->value,
        ];
    }
}
