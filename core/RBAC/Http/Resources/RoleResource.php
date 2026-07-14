<?php

declare(strict_types=1);

namespace Core\RBAC\Http\Resources;

use Core\RBAC\Infrastructure\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
final class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_system' => $this->is_system,
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('name')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
