<?php

declare(strict_types=1);

namespace Core\Billing\Infrastructure\Gateways;

use Core\Billing\Application\DTOs\CheckoutRequest;
use Core\Billing\Application\DTOs\CheckoutResult;
use Core\Billing\Application\DTOs\WebhookPayload;
use Core\Billing\Contracts\PaymentGatewayInterface;
use Core\Billing\Exceptions\InvalidWebhookSignatureException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Real PayFast integration — see docs/adr/010-payfast-payment-gateway.md
 * for why PayFast, and core/Billing/README.md for the overall flow.
 * Verified against PayFast's published sandbox request/response shapes;
 * a live sandbox merchant account is required for an end-to-end
 * verified run (tracked as an open item — see the ADR's Consequences).
 *
 * Checkout: a GET redirect to PayFast's "process" endpoint with a
 * signed query string — PayFast supports this simple redirect flow as
 * an alternative to an auto-submitting POST form.
 *
 * Webhook (ITN) trust chain, per PayFast's documented process:
 *   1. verifyWebhookSignature() — recompute the MD5 signature locally.
 *   2. confirmWithGateway() — source-IP allow-list, then a
 *      server-to-server POST back to PayFast's "validate" endpoint;
 *      only a "VALID" response is trusted.
 * Both must pass before parseWebhookPayload() is used for anything.
 */
final class PayFastGateway implements PaymentGatewayInterface
{
    private const LIVE_PROCESS_URL = 'https://www.payfast.co.za/eng/process';

    private const SANDBOX_PROCESS_URL = 'https://sandbox.payfast.co.za/eng/process';

    private const LIVE_VALIDATE_URL = 'https://www.payfast.co.za/eng/query/validate';

    private const SANDBOX_VALIDATE_URL = 'https://sandbox.payfast.co.za/eng/query/validate';

    /**
     * Hostnames PayFast documents as legitimate ITN senders. Resolved
     * to IPs at check time rather than hard-coded, since PayFast may
     * change the underlying addresses.
     */
    private const VALID_HOSTS = [
        'www.payfast.co.za',
        'sandbox.payfast.co.za',
        'w1w.payfast.co.za',
        'w2w.payfast.co.za',
    ];

    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'payfast';
    }

    public function isAvailable(): bool
    {
        return filled($this->config['merchant_id'] ?? null) && filled($this->config['merchant_key'] ?? null);
    }

    public function initiateCheckout(CheckoutRequest $request): CheckoutResult
    {
        $fields = [
            'merchant_id' => (string) $this->config['merchant_id'],
            'merchant_key' => (string) $this->config['merchant_key'],
            'return_url' => $request->returnUrl,
            'cancel_url' => $request->cancelUrl,
            'notify_url' => $request->notifyUrl,
            'm_payment_id' => $request->reference,
            'amount' => number_format($request->amount, 2, '.', ''),
            'item_name' => $request->itemName,
            'custom_str1' => $request->purpose->value,
        ];

        if ($request->isRecurring()) {
            $fields['subscription_type'] = '1';
            $fields['recurring_amount'] = $fields['amount'];
            $fields['frequency'] = (string) $request->recurringFrequency;
            if ($request->recurringCycles !== null) {
                $fields['cycles'] = (string) $request->recurringCycles;
            }
        }

        $fields['signature'] = $this->sign($fields);

        return new CheckoutResult($this->processUrl().'?'.http_build_query($fields, '', '&', PHP_QUERY_RFC1738));
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $posted = $request->except('signature');
        $expected = $this->sign($posted);

        return hash_equals($expected, (string) $request->input('signature', ''));
    }

    public function confirmWithGateway(Request $request): bool
    {
        if (($this->config['verify_source_ip'] ?? true) && ! $this->sourceIpIsTrusted($request)) {
            return false;
        }

        if (! ($this->config['confirm_with_gateway'] ?? true)) {
            return true;
        }

        $response = Http::asForm()->post($this->validateUrl(), $request->except('signature') + ['signature' => $request->input('signature')]);

        return $response->ok() && trim($response->body()) === 'VALID';
    }

    public function parseWebhookPayload(Request $request): WebhookPayload
    {
        $reference = $request->string('m_payment_id')->toString();
        if ($reference === '') {
            throw InvalidWebhookSignatureException::forGateway($this->name());
        }

        $status = $request->string('payment_status')->toString();

        return new WebhookPayload(
            reference: $reference,
            isSuccessful: strtoupper($status) === 'COMPLETE',
            amountGross: (float) $request->input('amount_gross', 0),
            rawStatus: $status,
            operationalFields: [
                'pf_payment_id' => $request->input('pf_payment_id'),
                'payment_status' => $status,
                'amount_gross' => $request->input('amount_gross'),
                'amount_fee' => $request->input('amount_fee'),
                'amount_net' => $request->input('amount_net'),
            ],
        );
    }

    private function sign(array $fields): string
    {
        $pairs = [];
        foreach ($fields as $key => $value) {
            if ($key === 'signature' || $value === null || $value === '') {
                continue;
            }
            $pairs[] = $key.'='.urlencode((string) $value);
        }

        $query = implode('&', $pairs);
        $passphrase = $this->config['passphrase'] ?? null;
        if (filled($passphrase)) {
            $query .= '&passphrase='.urlencode((string) $passphrase);
        }

        return md5($query);
    }

    private function sourceIpIsTrusted(Request $request): bool
    {
        $remote = $request->ip();
        foreach (self::VALID_HOSTS as $host) {
            $resolved = gethostbynamel($host) ?: [];
            if (in_array($remote, $resolved, true)) {
                return true;
            }
        }

        return false;
    }

    private function isSandbox(): bool
    {
        return (bool) ($this->config['sandbox'] ?? true);
    }

    private function processUrl(): string
    {
        return $this->isSandbox() ? self::SANDBOX_PROCESS_URL : self::LIVE_PROCESS_URL;
    }

    private function validateUrl(): string
    {
        return $this->isSandbox() ? self::SANDBOX_VALIDATE_URL : self::LIVE_VALIDATE_URL;
    }
}
