<?php

declare(strict_types=1);

namespace Core\Billing\Http\Controllers\Api\V1;

use Core\Billing\Application\PaymentWebhookService;
use Core\Billing\Exceptions\InvalidWebhookSignatureException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Public gateway notification endpoint — no auth, no CSRF (mounted
 * under the `api` middleware group). Trust comes entirely from the
 * gateway's own signature + server-to-server confirmation, verified
 * inside PaymentWebhookService before any state changes. See
 * core/Billing/README.md.
 */
final class WebhookController
{
    public function __construct(private readonly PaymentWebhookService $webhooks) {}

    public function payfast(Request $request): Response
    {
        try {
            $this->webhooks->handle($request, 'payfast');
        } catch (InvalidWebhookSignatureException) {
            // Deliberately generic — never echo back why a webhook was
            // rejected to an unauthenticated caller.
            return response('', 400);
        }

        return response('', 200);
    }
}
