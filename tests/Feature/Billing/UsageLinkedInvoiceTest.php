<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use Core\Billing\Application\InvoiceService;
use Core\Billing\Domain\Enums\InvoiceLineType;
use Core\Billing\Domain\Enums\InvoiceStatus;
use Core\Billing\Infrastructure\Models\Invoice;
use Core\Subscriptions\Domain\Enums\SubscriptionStatus;
use Core\Subscriptions\Infrastructure\Models\Plan;
use Core\Subscriptions\Infrastructure\Models\Subscription;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Modules\Organizations\Infrastructure\Models\Organization;
use Tests\TestCase;

final class UsageLinkedInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generated_invoice_carries_a_base_line_and_an_attendance_overage_line_over_the_plan_cap(): void
    {
        Notification::fake();
        [$organization, $subscription, $plan] = $this->activeSubscription('invoice-overage', maxLearners: 10);
        $service = app(InvoiceService::class);

        $invoice = $service->generateForPeriod($subscription, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), attendanceActiveLearnerCount: 13);

        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $lines = $invoice->lineItems;
        $this->assertCount(2, $lines);
        $base = $lines->firstWhere('line_type', InvoiceLineType::SubscriptionBase);
        $overage = $lines->firstWhere('line_type', InvoiceLineType::AttendanceOverage);
        $this->assertNotNull($base);
        $this->assertNotNull($overage);
        $this->assertSame(3, $overage->quantity);
        $this->assertEqualsWithDelta((float) $plan->price + 3 * (float) $plan->overage_rate_per_learner, (float) $invoice->total, 0.001);
    }

    public function test_usage_within_the_plan_cap_produces_only_the_base_line(): void
    {
        [$organization, $subscription, $plan] = $this->activeSubscription('invoice-within-cap', maxLearners: 500);
        $service = app(InvoiceService::class);

        $invoice = $service->generateForPeriod($subscription, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), attendanceActiveLearnerCount: 40);

        $this->assertCount(1, $invoice->lineItems);
        $this->assertEqualsWithDelta((float) $plan->price, (float) $invoice->total, 0.001);
    }

    public function test_regenerating_for_the_same_period_is_idempotent(): void
    {
        [$organization, $subscription] = $this->activeSubscription('invoice-idempotent', maxLearners: 10);
        $service = app(InvoiceService::class);

        $first = $service->generateForPeriod($subscription, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), attendanceActiveLearnerCount: 20);
        $second = $service->generateForPeriod($subscription, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), attendanceActiveLearnerCount: 999);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_overdue_issued_invoice_starts_the_subscription_grace_period(): void
    {
        Notification::fake();
        [$organization, $subscription] = $this->activeSubscription('invoice-overdue');
        $invoice = Invoice::query()->create([
            'organization_id' => $organization->id,
            'subscription_id' => $subscription->id,
            'billing_period_start' => now()->subMonth()->startOfMonth()->toDateString(),
            'billing_period_end' => now()->subMonth()->endOfMonth()->toDateString(),
            'status' => InvoiceStatus::Issued,
            'currency' => 'ZAR',
            'subtotal' => 1499.00,
            'total' => 1499.00,
            'due_date' => now()->subDay()->toDateString(),
            'issued_at' => now()->subDays(8),
        ]);

        $count = app(InvoiceService::class)->markOverdueAndGraceSubscriptions();

        $this->assertSame(1, $count);
        $this->assertSame(InvoiceStatus::Overdue, $invoice->fresh()->status);
        $this->assertSame(SubscriptionStatus::GracePeriod, $subscription->fresh()->status);
    }

    /**
     * @return array{0: Organization, 1: Subscription, 2: Plan}
     */
    private function activeSubscription(string $code, int $maxLearners = 500): array
    {
        $organization = Organization::query()->create(['name' => $code, 'code' => $code, 'type' => 'school']);
        $admin = User::factory()->create();
        $organization->administrators()->attach($admin->id, ['assigned_at' => now()]);
        $plan = Plan::query()->create([
            'key' => $code,
            'name' => ucfirst($code),
            'price' => 1499.00,
            'currency' => 'ZAR',
            'billing_cycle' => 'monthly',
            'max_learners' => $maxLearners,
            'max_staff' => 25,
            'ai_allowance' => 500,
            'overage_rate_per_learner' => 15.00,
            'is_active' => true,
        ]);
        $subscription = Subscription::query()->create([
            'subscriber_type' => Organization::class,
            'subscriber_id' => $organization->id,
            'plan' => $plan->key,
            'billing_cycle' => 'monthly',
            'status' => SubscriptionStatus::Active,
            'started_at' => now()->subMonths(2)->toDateString(),
        ]);

        return [$organization, $subscription, $plan];
    }
}
