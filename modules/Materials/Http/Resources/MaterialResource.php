<?php

declare(strict_types=1);

namespace Modules\Materials\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Materials\Infrastructure\Models\Material;

/**
 * @mixin Material
 */
final class MaterialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'title' => $this->title,
            'subject' => $this->whenLoaded('subject', fn () => $this->subject?->name),
            'status' => $this->status->value,
            'failure_message' => $this->failure_message,
            'chunk_count' => $this->whenCounted('chunks'),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
