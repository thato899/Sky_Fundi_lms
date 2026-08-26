<?php

declare(strict_types=1);

namespace Core\Subscriptions\Http\Resources;

use Core\Subscriptions\Infrastructure\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plan
 */
final class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'price' => (string) $this->price,
            'currency' => $this->currency,
            'billing_cycle' => $this->billing_cycle->value,
            'max_learners' => $this->max_learners,
            'max_staff' => $this->max_staff,
            'ai_allowance' => $this->ai_allowance,
        ];
    }
}
