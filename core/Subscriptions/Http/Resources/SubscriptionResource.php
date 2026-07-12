<?php

declare(strict_types=1);

namespace Core\Subscriptions\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Core\Subscriptions\Infrastructure\Models\Subscription
 */
final class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'license_id' => $this->license_id,
            'plan' => $this->plan,
            'billing_cycle' => $this->billing_cycle->value,
            'status' => $this->status->value,
            'started_at' => $this->started_at?->toDateString(),
            'renewal_date' => $this->renewal_date?->toDateString(),
            'grace_period_ends_at' => $this->grace_period_ends_at?->toDateString(),
            'max_users' => $this->max_users,
            'current_users' => $this->current_users,
            'max_storage_mb' => $this->max_storage_mb,
            'current_storage_mb' => $this->current_storage_mb,
            'is_over_user_limit' => $this->isOverUserLimit(),
            'is_over_storage_limit' => $this->isOverStorageLimit(),
            'module_access' => $this->module_access,
        ];
    }
}
