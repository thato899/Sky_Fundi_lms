<?php

declare(strict_types=1);

namespace Core\Billing\Application\DTOs;

use Core\Billing\Domain\Enums\PaymentPurpose;

/**
 * What CheckoutService asks a PaymentGatewayInterface to build a
 * redirect checkout for. `reference` is our own identifier (the
 * Payment row's id) — the gateway echoes it back on its webhook so we
 * can match the notification to this attempt without trusting
 * anything the gateway invented itself.
 */
final readonly class CheckoutRequest
{
    public function __construct(
        public string $reference,
        public string $itemName,
        public float $amount,
        public string $currency,
        public PaymentPurpose $purpose,
        public string $returnUrl,
        public string $cancelUrl,
        public string $notifyUrl,
        public ?string $recurringFrequency = null,
        public ?int $recurringCycles = null,
    ) {}

    public function isRecurring(): bool
    {
        return $this->recurringFrequency !== null;
    }
}
