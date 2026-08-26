<?php

declare(strict_types=1);

namespace Core\Billing\Exceptions;

final class InvalidWebhookSignatureException extends BillingException
{
    public static function forGateway(string $gateway): self
    {
        return new self("Webhook signature verification failed for gateway \"{$gateway}\".");
    }
}
