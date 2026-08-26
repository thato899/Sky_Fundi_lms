<?php

declare(strict_types=1);

namespace Core\Billing\Http\Resources;

use Core\Billing\Infrastructure\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
final class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gateway' => $this->gateway,
            'purpose' => $this->purpose->value,
            'status' => $this->status->value,
            'amount' => (string) $this->amount,
            'currency' => $this->currency,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
