<?php

declare(strict_types=1);

namespace Core\Billing\Application;

use Core\Billing\Domain\Enums\PaymentPurpose;
use Core\Billing\Domain\Enums\PaymentStatus;
use Core\Billing\Events\PaymentFailed;
use Core\Billing\Events\PaymentReceived;
use Core\Billing\Exceptions\InvalidWebhookSignatureException;
use Core\Billing\Infrastructure\Models\Invoice;
use Core\Billing\Infrastructure\Models\Payment;
use Core\Subscriptions\Application\SubscriptionService;
use Core\Subscriptions\Infrastructure\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Organizations\Infrastructure\Models\Organization;

/**
 * Processes a gateway webhook after its trust checks have passed —
 * the ONLY place a Payment is ever marked Completed/Failed and the
 * only place a subscription-signup payment starts a Subscription or
 * an invoice-settlement payment marks an Invoice paid. See
 * core/Billing/README.md.
 */
final class PaymentWebhookService
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly SubscriptionService $subscriptions,
        private readonly InvoiceService $invoices,
    ) {}

    /**
     * @throws InvalidWebhookSignatureException
     */
    public function handle(Request $request, string $gatewayName): void
    {
        $gateway = $this->gateways->gateway($gatewayName);

        if (! $gateway->verifyWebhookSignature($request) || ! $gateway->confirmWithGateway($request)) {
            throw InvalidWebhookSignatureException::forGateway($gatewayName);
        }

        $payload = $gateway->parseWebhookPayload($request);

        $payment = Payment::query()
            ->where('gateway', $gatewayName)
            ->where('gateway_reference', $payload->reference)
            ->first();

        if ($payment === null) {
            // Unknown reference — nothing to process. Not an error: the
            // gateway retries notifications, and a reference we never
            // created (or already garbage-collected) is simply ignored.
            return;
        }

        // Idempotent: a replayed ITN for an already-settled payment is a
        // no-op rather than a duplicate subscription/invoice transition.
        if ($payment->status !== PaymentStatus::Pending) {
            return;
        }

        DB::transaction(function () use ($payment, $payload): void {
            if ($payload->isSuccessful) {
                $this->settle($payment, $payload->operationalFields);
            } else {
                $this->fail($payment, $payload->operationalFields);
            }
        });
    }

    private function settle(Payment $payment, array $gatewayResponse): void
    {
        $payment->update([
            'status' => PaymentStatus::Completed,
            'gateway_response' => $gatewayResponse,
            'confirmed_at' => now(),
        ]);

        match ($payment->purpose) {
            PaymentPurpose::SubscriptionSignup => $this->startSubscription($payment),
            PaymentPurpose::InvoiceSettlement => $this->settleInvoice($payment),
        };

        PaymentReceived::dispatch($payment->fresh());
    }

    private function fail(Payment $payment, array $gatewayResponse): void
    {
        $payment->update([
            'status' => PaymentStatus::Failed,
            'gateway_response' => $gatewayResponse,
        ]);

        PaymentFailed::dispatch($payment->fresh());
    }

    private function startSubscription(Payment $payment): void
    {
        $planKey = (string) ($payment->getMeta('plan_key') ?? '');
        $plan = Plan::findByKey($planKey);
        if ($plan === null) {
            return;
        }

        $subscription = $this->subscriptions->start([
            'subscriber_type' => Organization::class,
            'subscriber_id' => $payment->organization_id,
            'plan' => $plan->key,
            'billing_cycle' => $plan->billing_cycle->value,
            'renewal_date' => now()->addMonthNoOverflow()->toDateString(),
            'max_users' => $plan->max_learners,
            'external_reference' => $payment->gateway_reference,
        ]);

        $payment->update(['subscription_id' => $subscription->getKey()]);
    }

    private function settleInvoice(Payment $payment): void
    {
        $invoice = Invoice::query()->find($payment->invoice_id);
        if ($invoice === null) {
            return;
        }

        $this->invoices->markPaid($invoice, $payment);
    }
}
