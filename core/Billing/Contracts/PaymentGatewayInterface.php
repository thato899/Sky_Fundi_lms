<?php

declare(strict_types=1);

namespace Core\Billing\Contracts;

use Core\Billing\Application\DTOs\CheckoutRequest;
use Core\Billing\Application\DTOs\CheckoutResult;
use Core\Billing\Application\DTOs\WebhookPayload;
use Core\Billing\Exceptions\InvalidWebhookSignatureException;
use Illuminate\Http\Request;

/**
 * The contract every payment gateway adapter implements. No module or
 * Core service may talk to a gateway SDK/API directly — everything
 * resolves through Core\Billing\Application\GatewayManager, which
 * selects and calls a gateway through this interface. Mirrors
 * Core\AIGateway\Contracts\AIProviderInterface's shape — see
 * docs/adr/010-payfast-payment-gateway.md.
 */
interface PaymentGatewayInterface
{
    /**
     * The gateway's registry key, e.g. "payfast".
     */
    public function name(): string;

    /**
     * Whether this gateway is currently configured (has credentials).
     */
    public function isAvailable(): bool;

    /**
     * Builds a redirect checkout for the given request.
     */
    public function initiateCheckout(CheckoutRequest $request): CheckoutResult;

    /**
     * Local signature verification of an inbound webhook request —
     * the first of the gateway's documented trust checks. Does not by
     * itself prove the notification is genuine; see confirmWithGateway().
     */
    public function verifyWebhookSignature(Request $request): bool;

    /**
     * Server-to-server confirmation with the gateway that the webhook
     * data is genuine (PayFast's ITN "validate" callback and
     * equivalents) — the second trust check, done over the network so
     * it must be a distinct step tests can fake independently of
     * verifyWebhookSignature().
     */
    public function confirmWithGateway(Request $request): bool;

    /**
     * Normalizes a verified webhook request into a WebhookPayload.
     * Callers must have already called verifyWebhookSignature() and
     * confirmWithGateway() — this method does not re-check trust.
     *
     * @throws InvalidWebhookSignatureException if the payload is malformed
     */
    public function parseWebhookPayload(Request $request): WebhookPayload;
}
