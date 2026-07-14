<?php

declare(strict_types=1);

namespace Core\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MembershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'organization_id' => $this->organization_id, 'user_id' => $this->user_id, 'status' => $this->status->value, 'role' => $this->role?->name, 'is_default' => $this->is_default, 'joined_at' => $this->joined_at?->toIso8601String(), 'last_active_at' => $this->last_active_at?->toIso8601String()];
    }
}
