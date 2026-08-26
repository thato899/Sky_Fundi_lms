<?php

declare(strict_types=1);

namespace Core\Billing\Http\Resources;

use Core\Billing\Infrastructure\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
final class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'billing_period_start' => $this->billing_period_start->toDateString(),
            'billing_period_end' => $this->billing_period_end->toDateString(),
            'currency' => $this->currency,
            'subtotal' => (string) $this->subtotal,
            'total' => (string) $this->total,
            'due_date' => $this->due_date?->toDateString(),
            'issued_at' => $this->issued_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'is_payable' => $this->isPayable(),
            'line_items' => $this->whenLoaded('lineItems', fn () => $this->lineItems->map(fn ($line) => [
                'line_type' => $line->line_type->value,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => (string) $line->unit_price,
                'amount' => (string) $line->amount,
            ])),
        ];
    }
}
