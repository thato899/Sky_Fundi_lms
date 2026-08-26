<?php

declare(strict_types=1);

namespace Core\Billing\Application\DTOs;

/**
 * A gateway's webhook notification, normalized. `reference` is our
 * own `CheckoutRequest::$reference` echoed back — the only field
 * trusted to look up the matching Payment row. `operationalFields`
 * carries only non-personal, operationally useful data (gateway
 * transaction id, payment status string) for storage on
 * `payments.gateway_response` — the gateway adapter is responsible
 * for excluding payer name/email/other personal data here, per
 * AGENTS.md's prohibition on logging personal data.
 */
final readonly class WebhookPayload
{
    public function __construct(
        public string $reference,
        public bool $isSuccessful,
        public float $amountGross,
        public string $rawStatus,
        public array $operationalFields = [],
    ) {}
}
