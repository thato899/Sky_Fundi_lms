<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use Core\Billing\Domain\Enums\InvoiceStatus;
use Core\Billing\Domain\Enums\PaymentPurpose;
use Core\Billing\Domain\Enums\PaymentStatus;
use Core\Billing\Infrastructure\Models\Invoice;
use Core\Billing\Infrastructure\Models\Payment;
use Core\Notifications\Infrastructure\Notifications\CoreNotification;
use Core\Subscriptions\Domain\Enums\SubscriptionStatus;
use Core\Subscriptions\Infrastructure\Models\Plan;
use Core\Subscriptions\Infrastructure\Models\Subscription;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Infrastructure\Models\OrganizationModule;
use Tests\TestCase;

final class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const PASSPHRASE = 'test-passphrase';

    public function test_verified_webhook_settles_a_signup_payment_and_starts_the_subscription(): void
    {
        Http::fake(['*/eng/query/validate' => Http::response('VALID', 200)]);
        Notification::fake();
        $organization = $this->organization('webhook-signup');
        $plan = $this->plan('growth');
        $payment = $this->pendingPayment($organization, PaymentPurpose::SubscriptionSignup, $plan->price, ['plan_key' => $plan->key]);

        $this->postSignedWebhook($payment, 'COMPLETE')->assertOk();

        $payment = $payment->fresh();
        $this->assertSame(PaymentStatus::Completed, $payment->status);
        $this->assertNotNull($payment->subscription_id);
        $subscription = Subscription::query()->findOrFail($payment->subscription_id);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame($plan->key, $subscription->plan);
        // Gateway response never carries payer personal data, only
        // operational fields.
        $this->assertArrayNotHasKey('email_address', $payment->gateway_response ?? []);
    }

    public function test_invalid_signature_is_rejected_and_payment_is_untouched(): void
    {
        Http::fake(['*/eng/query/validate' => Http::response('VALID', 200)]);
        $organization = $this->organization('webhook-bad-sig');
        $plan = $this->plan('growth');
        $payment = $this->pendingPayment($organization, PaymentPurpose::SubscriptionSignup, $plan->price, ['plan_key' => $plan->key]);

        $fields = $this->fields($payment, 'COMPLETE');
        $fields['signature'] = 'not-a-real-signature';
        $this->post('/api/v1/billing/webhooks/payfast', $fields)->assertStatus(400);

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_failed_payment_marks_payment_failed_and_notifies_administrators(): void
    {
        Http::fake(['*/eng/query/validate' => Http::response('VALID', 200)]);
        Notification::fake();
        $organization = $this->organization('webhook-failed');
        $admin = User::factory()->create();
        $organization->administrators()->attach($admin->id, ['assigned_at' => now()]);
        $plan = $this->plan('growth');
        $payment = $this->pendingPayment($organization, PaymentPurpose::SubscriptionSignup, $plan->price, ['plan_key' => $plan->key]);

        $this->postSignedWebhook($payment, 'FAILED')->assertOk();

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->subscription_id);
        Notification::assertSentTo($admin, CoreNotification::class);
    }

    public function test_replayed_webhook_for_an_already_settled_payment_is_a_no_op(): void
    {
        Http::fake(['*/eng/query/validate' => Http::response('VALID', 200)]);
        Notification::fake();
        $organization = $this->organization('webhook-replay');
        $plan = $this->plan('growth');
        $payment = $this->pendingPayment($organization, PaymentPurpose::SubscriptionSignup, $plan->price, ['plan_key' => $plan->key]);

        $this->postSignedWebhook($payment, 'COMPLETE')->assertOk();
        $firstSubscriptionId = $payment->fresh()->subscription_id;
        $this->assertSame(1, Subscription::query()->count());

        $this->postSignedWebhook($payment, 'COMPLETE')->assertOk();

        $this->assertSame(1, Subscription::query()->count());
        $this->assertSame($firstSubscriptionId, $payment->fresh()->subscription_id);
    }

    public function test_verified_webhook_settles_an_invoice_payment(): void
    {
        Http::fake(['*/eng/query/validate' => Http::response('VALID', 200)]);
        Notification::fake();
        $organization = $this->organization('webhook-invoice');
        $plan = $this->plan('growth');
        $subscription = Subscription::query()->create([
            'subscriber_type' => Organization::class,
            'subscriber_id' => $organization->id,
            'plan' => $plan->key,
            'billing_cycle' => 'monthly',
            'status' => SubscriptionStatus::Active,
            'started_at' => now()->subMonth()->toDateString(),
        ]);
        $invoice = Invoice::query()->create([
            'organization_id' => $organization->id,
            'subscription_id' => $subscription->id,
            'billing_period_start' => now()->startOfMonth()->toDateString(),
            'billing_period_end' => now()->endOfMonth()->toDateString(),
            'status' => InvoiceStatus::Issued,
            'currency' => 'ZAR',
            'subtotal' => 1499.00,
            'total' => 1499.00,
            'due_date' => now()->addDays(7)->toDateString(),
            'issued_at' => now(),
        ]);
        $payment = $this->pendingPayment($organization, PaymentPurpose::InvoiceSettlement, $invoice->total, [], $invoice->id, $subscription->id);

        $this->postSignedWebhook($payment, 'COMPLETE')->assertOk();

        $invoice = $invoice->fresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertNotNull($invoice->paid_at);
    }

    private function postSignedWebhook(Payment $payment, string $status)
    {
        return $this->post('/api/v1/billing/webhooks/payfast', $this->fields($payment, $status));
    }

    private function fields(Payment $payment, string $status): array
    {
        $fields = [
            'm_payment_id' => $payment->gateway_reference,
            'pf_payment_id' => '999888777',
            'payment_status' => $status,
            'amount_gross' => number_format((float) $payment->amount, 2, '.', ''),
            'amount_fee' => '-5.00',
            'amount_net' => number_format((float) $payment->amount - 5, 2, '.', ''),
        ];
        $fields['signature'] = $this->payFastSignature($fields);

        return $fields;
    }

    /**
     * Mirrors PayFast's documented signature algorithm — the same one
     * Core\Billing\Infrastructure\Gateways\PayFastGateway implements —
     * so this test proves the real endpoint against the real contract
     * rather than a mocked gateway.
     */
    private function payFastSignature(array $fields): string
    {
        $pairs = [];
        foreach ($fields as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $pairs[] = $key.'='.urlencode((string) $value);
        }
        $query = implode('&', $pairs).'&passphrase='.urlencode(self::PASSPHRASE);

        return md5($query);
    }

    private function pendingPayment(Organization $organization, PaymentPurpose $purpose, mixed $amount, array $metadata = [], ?string $invoiceId = null, ?string $subscriptionId = null): Payment
    {
        $payment = Payment::create([
            'organization_id' => $organization->id,
            'subscription_id' => $subscriptionId,
            'invoice_id' => $invoiceId,
            'gateway' => 'payfast',
            'purpose' => $purpose,
            'status' => PaymentStatus::Pending,
            'amount' => $amount,
            'currency' => 'ZAR',
            'metadata' => $metadata,
        ]);
        $payment->update(['gateway_reference' => (string) $payment->getKey()]);

        return $payment;
    }

    private function organization(string $code): Organization
    {
        $organization = Organization::query()->create(['name' => $code, 'code' => $code, 'type' => 'school']);
        OrganizationModule::query()->create(['organization_id' => $organization->id, 'module_name' => 'learners', 'enabled' => true]);

        return $organization;
    }

    private function plan(string $key): Plan
    {
        return Plan::query()->firstOrCreate(['key' => $key], [
            'name' => ucfirst($key),
            'price' => 1499.00,
            'currency' => 'ZAR',
            'billing_cycle' => 'monthly',
            'max_learners' => 500,
            'max_staff' => 25,
            'ai_allowance' => 500,
            'overage_rate_per_learner' => 15.00,
            'is_active' => true,
        ]);
    }
}
