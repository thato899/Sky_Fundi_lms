<?php

declare(strict_types=1);

namespace Core\Billing\Domain\Enums;

/**
 * What a gateway checkout was started for — encoded into the
 * gateway's "custom" passthrough field so the webhook handler knows
 * whether a confirmed payment should start a Subscription or settle
 * an already-issued Invoice. See Core\Billing\Application\CheckoutService
 * and Core\Billing\Application\PaymentWebhookService.
 */
enum PaymentPurpose: string
{
    case SubscriptionSignup = 'subscription_signup';
    case InvoiceSettlement = 'invoice_settlement';
}
