<?php

declare(strict_types=1);

namespace Core\Billing\Exceptions;

final class GatewayUnavailableException extends BillingException
{
    public static function notConfigured(string $gateway): self
    {
        return new self("Payment gateway \"{$gateway}\" is not configured.");
    }

    public static function unknown(string $gateway): self
    {
        return new self("Payment gateway \"{$gateway}\" is not registered.");
    }
}
