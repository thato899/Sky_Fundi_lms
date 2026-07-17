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
        $license = $this->resource;
        assert($license instanceof License);

        return [
            'id' => $license->getKey(),
            'license_key' => $license->getAttribute('license_key'),
            'tier' => $license->getAttribute('tier')->value,
            'status' => $license->getAttribute('status')->value,
            'licensee_type' => $license->getAttribute('licensee_type'),
            'licensee_id' => $license->getAttribute('licensee_id'),
            'activation_date' => $license->getAttribute('activation_date')?->toDateString(),
            'expiry_date' => $license->getAttribute('expiry_date')?->toDateString(),
            'renewal_date' => $license->getAttribute('renewal_date')?->toDateString(),
            'max_users' => $license->getAttribute('max_users'),
            'max_learners' => $license->getAttribute('max_learners'),
            'max_storage_mb' => $license->getAttribute('max_storage_mb'),
            'enabled_modules' => $license->getAttribute('enabled_modules'),
            'ai_provider' => $license->getAttribute('ai_provider'),
            'support_level' => $license->getAttribute('support_level'),
            'is_valid' => $license->getAttribute('status')->isUsable() && ! $license->isExpired(),
            'created_at' => $license->getAttribute('created_at')?->toIso8601String(),
        ];
    }
}
