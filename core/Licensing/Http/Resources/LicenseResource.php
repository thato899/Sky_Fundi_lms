<?php

declare(strict_types=1);

namespace Core\Licensing\Http\Resources;

use Core\Licensing\Infrastructure\Models\License;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin License
 */
final class LicenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'license_key' => $this->license_key,
            'tier' => $this->tier->value,
            'status' => $this->status->value,
            'licensee_type' => $this->licensee_type,
            'licensee_id' => $this->licensee_id,
            'activation_date' => $this->activation_date?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'renewal_date' => $this->renewal_date?->toDateString(),
            'max_users' => $this->max_users,
            'max_storage_mb' => $this->max_storage_mb,
            'enabled_modules' => $this->enabled_modules,
            'ai_provider' => $this->ai_provider,
            'support_level' => $this->support_level,
            'is_valid' => $this->status->isUsable() && ! $this->isExpired(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
