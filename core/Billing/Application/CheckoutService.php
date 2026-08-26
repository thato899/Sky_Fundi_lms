<?php

declare(strict_types=1);

namespace Core\Billing\Application;

use Core\Billing\Application\DTOs\CheckoutRequest;
use Core\Billing\Application\DTOs\CheckoutResult;
use Core\Billing\Domain\Enums\PaymentPurpose;
use Core\Billing\Domain\Enums\PaymentStatus;
use Core\Billing\Exceptions\BillingException;
use Core\Billing\Infrastructure\Models\Invoice;
use Core\Billing\Infrastructure\Models\Payment;
use Core\Subscriptions\Domain\Enums\BillingCycle;
use Core\Subscriptions\Infrastructure\Models\Plan;
use Illuminate\Support\Facades\URL;
use Modules\Organizations\Infrastructure\Models\Organization;

/**
 * Builds a gateway checkout for either a new subscription signup/plan
 * change or settling an already-issued Invoice — see
 * core/Billing/README.md. Always creates a Pending Payment row first
 * so the webhook has something verified to transition; never trusts a
 * client-reported "paid" state.
 */
final class CheckoutService
{
    public function __construct(private readonly GatewayManager $gateways) {}

    public function startSubscriptionCheckout(Organization $organization, Plan $plan, string $returnUrl, string $cancelUrl): CheckoutResult
    {
        if (! $plan->is_active) {
            throw new BillingException("Plan \"{$plan->key}\" is not available for new subscriptions.");
        }

        $payment = $this->createPendingPayment([
            'organization_id' => $organization->getKey(),
            'purpose' => PaymentPurpose::SubscriptionSignup,
            'amount' => $plan->price,
            'currency' => $plan->currency,
            'metadata' => ['plan_key' => $plan->key],
        ]);

        return $this->gateways->gateway()->initiateCheckout(new CheckoutRequest(
            reference: $payment->gateway_reference,
            itemName: "Sky Fundi — {$plan->name} plan",
            amount: (float) $plan->price,
            currency: $plan->currency,
            purpose: PaymentPurpose::SubscriptionSignup,
            returnUrl: $returnUrl,
            cancelUrl: $cancelUrl,
            notifyUrl: $this->notifyUrl(),
            recurringFrequency: $this->payFastFrequency($plan->billing_cycle),
        ));
    }

    public function startInvoiceCheckout(Invoice $invoice, string $returnUrl, string $cancelUrl): CheckoutResult
    {
        if (! $invoice->isPayable()) {
            throw new BillingException('This invoice is not payable.');
        }

        $payment = $this->createPendingPayment([
            'organization_id' => $invoice->organization_id,
            'subscription_id' => $invoice->subscription_id,
            'invoice_id' => $invoice->getKey(),
            'purpose' => PaymentPurpose::InvoiceSettlement,
            'amount' => $invoice->total,
            'currency' => $invoice->currency,
        ]);

        return $this->gateways->gateway()->initiateCheckout(new CheckoutRequest(
            reference: $payment->gateway_reference,
            itemName: 'Sky Fundi invoice '.$invoice->billing_period_start->toDateString().' – '.$invoice->billing_period_end->toDateString(),
            amount: (float) $invoice->total,
            currency: $invoice->currency,
            purpose: PaymentPurpose::InvoiceSettlement,
            returnUrl: $returnUrl,
            cancelUrl: $cancelUrl,
            notifyUrl: $this->notifyUrl(),
        ));
    }

    private function createPendingPayment(array $attributes): Payment
    {
        $payment = Payment::create($attributes + [
            'gateway' => (string) config('billing.default_gateway'),
            'status' => PaymentStatus::Pending,
        ]);

        // The payment's own id doubles as the gateway's `m_payment_id` —
        // one less identifier to generate and guaranteed unique.
        $payment->update(['gateway_reference' => (string) $payment->getKey()]);

        return $payment;
    }

    private function notifyUrl(): string
    {
        return URL::route('billing.webhooks.payfast');
    }

    private function payFastFrequency(BillingCycle $cycle): string
    {
        // PayFast's documented recurring `frequency` codes.
        return match ($cycle) {
            BillingCycle::Annual => '6',
            default => '3',
        };
    }
}
