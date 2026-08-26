<?php

declare(strict_types=1);

use Core\Billing\Infrastructure\Gateways\PayFastGateway;

/*
|--------------------------------------------------------------------------
| Billing Configuration
|--------------------------------------------------------------------------
|
| See core/Billing/README.md and docs/adr/010-payfast-payment-gateway.md.
| No module or Core service may talk to a payment gateway SDK/API
| directly — everything resolves through
| Core\Billing\Application\GatewayManager, which uses this
| configuration to resolve a gateway by name.
|
*/

return [
    'default_gateway' => env('BILLING_DEFAULT_GATEWAY', 'payfast'),

    'gateways' => [
        'payfast' => [
            'driver' => PayFastGateway::class,
            'merchant_id' => env('PAYFAST_MERCHANT_ID'),
            'merchant_key' => env('PAYFAST_MERCHANT_KEY'),
            // Shared secret configured on the PayFast merchant account —
            // never sent by PayFast in any request; only used locally to
            // sign outgoing checkouts and verify incoming webhooks.
            'passphrase' => env('PAYFAST_PASSPHRASE'),
            'sandbox' => (bool) env('PAYFAST_SANDBOX', true),
            // Both true by default (real ITN validation, see
            // Infrastructure/Gateways/PayFastGateway.php) — turned off
            // only for tests that fake the HTTP boundary instead.
            'verify_source_ip' => (bool) env('PAYFAST_VERIFY_SOURCE_IP', true),
            'confirm_with_gateway' => (bool) env('PAYFAST_CONFIRM_WITH_GATEWAY', true),
        ],
    ],

    // Fallback overage rate when a Plan row has none set — see
    // Application\InvoiceService.
    'default_overage_rate_per_learner' => (float) env('BILLING_DEFAULT_OVERAGE_RATE', 15.00),

    'invoice' => [
        'due_days' => (int) env('BILLING_INVOICE_DUE_DAYS', 7),
    ],
];
