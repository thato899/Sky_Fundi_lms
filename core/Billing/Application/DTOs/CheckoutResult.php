<?php

declare(strict_types=1);

namespace Core\Billing\Application\DTOs;

/**
 * Where to send the payer to complete a checkout the gateway built.
 */
final readonly class CheckoutResult
{
    public function __construct(
        public string $redirectUrl,
    ) {}
}
