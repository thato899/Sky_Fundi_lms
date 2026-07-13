<?php

declare(strict_types=1);

namespace Modules\Organizations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Modules\Organizations\Infrastructure\Models\Organization */
final class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'code' => $this->code, 'type' => $this->type, 'status' => $this->status->value, 'contact' => ['email' => $this->email, 'telephone' => $this->telephone, 'website' => $this->website], 'address' => ['address' => $this->address, 'country' => $this->country, 'province' => $this->province, 'city' => $this->city, 'postal_code' => $this->postal_code], 'timezone' => $this->timezone, 'language' => $this->language, 'currency' => $this->currency, 'usage' => ['storage_quota' => $this->storage_quota, 'current_storage' => $this->current_storage, 'maximum_users' => $this->maximum_users, 'current_users' => $this->current_users], 'license' => ['type' => $this->license_type, 'expires_at' => $this->license_expires_at?->toDateString(), 'renews_at' => $this->license_renews_at?->toDateString(), 'support_plan' => $this->support_plan], 'administrators' => $this->whenLoaded('administrators'), 'modules' => $this->whenLoaded('modules')];
    }
}
