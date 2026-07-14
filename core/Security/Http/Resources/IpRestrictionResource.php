<?php

declare(strict_types=1);

namespace Core\Security\Http\Resources;

use Core\Security\Infrastructure\Models\IpRestriction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin IpRestriction
 */
final class IpRestrictionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scope_type' => $this->scope_type,
            'scope_id' => $this->scope_id,
            'type' => $this->type->value,
            'ip_cidr' => $this->ip_cidr,
            'description' => $this->description,
            'is_active' => $this->is_active,
        ];
    }
}
