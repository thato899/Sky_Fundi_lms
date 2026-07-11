<?php

declare(strict_types=1);

namespace Core\Modules\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Core\Modules\Infrastructure\Models\ModuleRegistration
 */
final class ModuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'version' => $this->version,
            'description' => $this->description,
            'author' => $this->author,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'dependencies' => $this->dependencies,
            'tenant_types' => $this->tenant_types,
            'enabled_for_tenants' => $this->enabled_for_tenants,
            'installed_at' => $this->installed_at?->toIso8601String(),
            'enabled_at' => $this->enabled_at?->toIso8601String(),
        ];
    }
}
