<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use Core\Billing\Database\Seeders\BillingPermissionSeeder;
use Core\Billing\Domain\Enums\PaymentPurpose;
use Core\Billing\Domain\Enums\PaymentStatus;
use Core\Billing\Infrastructure\Models\Payment;
use Core\Identity\Infrastructure\Models\Membership;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Subscriptions\Infrastructure\Models\Plan;
use Core\Users\Infrastructure\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Organizations\Infrastructure\Models\Organization;
use Tests\TestCase;

final class SubscriptionCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_org_admin_starts_subscription_checkout_and_creates_a_pending_payment(): void
    {
        [$organization, $admin] = $this->orgAdmin('checkout-happy');
        $plan = $this->plan('growth');

        $response = $this->actingAs($admin, 'sanctum')
            ->withHeader('X-Organization-Id', $organization->id)
            ->postJson('/api/v1/billing/checkout/subscription', [
                'plan_key' => $plan->key,
                'return_url' => 'https://example.test/return',
                'cancel_url' => 'https://example.test/cancel',
            ])
            ->assertOk();

        $redirectUrl = $response->json('data.redirect_url');
        $this->assertStringContainsString('sandbox.payfast.co.za/eng/process', $redirectUrl);
        $this->assertStringContainsString('signature=', $redirectUrl);

        $payment = Payment::query()->where('organization_id', $organization->id)->firstOrFail();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(PaymentPurpose::SubscriptionSignup, $payment->purpose);
        $this->assertSame((string) $payment->getKey(), $payment->gateway_reference);
        $this->assertSame('growth', $payment->getMeta('plan_key'));
        $this->assertSame(1, Payment::query()->count());
    }

    public function test_member_without_billing_permission_is_forbidden(): void
    {
        [$organization, , $membership] = $this->orgAdmin('checkout-forbidden', grantBilling: false);
        $plan = $this->plan('checkout-forbidden');

        $this->actingAs($membership->user, 'sanctum')
            ->withHeader('X-Organization-Id', $organization->id)
            ->postJson('/api/v1/billing/checkout/subscription', [
                'plan_key' => $plan->key,
                'return_url' => 'https://example.test/return',
                'cancel_url' => 'https://example.test/cancel',
            ])
            ->assertForbidden();

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_unknown_plan_key_is_rejected(): void
    {
        [$organization, $admin] = $this->orgAdmin('checkout-unknown-plan');

        $this->actingAs($admin, 'sanctum')
            ->withHeader('X-Organization-Id', $organization->id)
            ->postJson('/api/v1/billing/checkout/subscription', [
                'plan_key' => 'does-not-exist',
                'return_url' => 'https://example.test/return',
                'cancel_url' => 'https://example.test/cancel',
            ])
            ->assertUnprocessable();

        $this->assertSame(0, Payment::query()->count());
    }

    /**
     * @return array{0: Organization, 1: User, 2: Membership}
     */
    private function orgAdmin(string $code, bool $grantBilling = true): array
    {
        $this->seed(PermissionSeeder::class);
        if ($grantBilling) {
            $this->seed(BillingPermissionSeeder::class);
        }
        $organization = Organization::query()->create(['name' => $code, 'code' => $code, 'type' => 'school']);
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(['name' => 'Organization Administrator'], ['is_system' => false]);
        $membership = Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active', 'is_default' => true]);

        return [$organization, $user, $membership];
    }

    private function plan(string $key): Plan
    {
        return Plan::query()->create([
            'key' => $key,
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
