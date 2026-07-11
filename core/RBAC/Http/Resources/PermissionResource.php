<?php

declare(strict_types=1);

namespace Core\RBAC\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Core\RBAC\Infrastructure\Models\Permission
 */
final class PermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'module' => $this->module,
            'description' => $this->description,
        ];
    }
}
