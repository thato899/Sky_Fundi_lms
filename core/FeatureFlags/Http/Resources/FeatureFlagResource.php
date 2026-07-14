<?php

declare(strict_types=1);

namespace Core\FeatureFlags\Http\Resources;

use Core\FeatureFlags\Infrastructure\Models\FeatureFlag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FeatureFlag
 */
final class FeatureFlagResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'is_enabled_globally' => $this->is_enabled_globally,
            'overrides' => $this->whenLoaded('overrides', fn () => $this->overrides->map(fn ($o) => [
                'scope_type' => $o->scope_type,
                'scope_id' => $o->scope_id,
                'is_enabled' => $o->is_enabled,
            ])),
        ];
    }
}
