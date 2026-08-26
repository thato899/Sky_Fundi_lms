<?php

declare(strict_types=1);

namespace Core\Subscriptions\Database\Seeders;

use Core\Subscriptions\Domain\Enums\BillingCycle;
use Core\Subscriptions\Infrastructure\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Seeds the real plan catalog — see docs/adr/010-payfast-payment-gateway.md.
 * Values mirror the previous `config('hackathon.plans')` demo array so
 * existing plan keys ("starter", "growth", "school_pro") keep working
 * unchanged for any Subscription already referencing them. Idempotent:
 * safe to rerun, never overwrites an organization-customised price.
 */
final class PlansSeeder extends Seeder
{
    private const PLANS = [
        ['key' => 'starter', 'name' => 'Starter', 'price' => 499.00, 'max_learners' => 100, 'max_staff' => 5, 'ai_allowance' => 50],
        ['key' => 'growth', 'name' => 'Growth', 'price' => 1499.00, 'max_learners' => 500, 'max_staff' => 25, 'ai_allowance' => 500],
        ['key' => 'school_pro', 'name' => 'School Pro', 'price' => 3999.00, 'max_learners' => 1500, 'max_staff' => 75, 'ai_allowance' => 2000],
    ];

    /**
     * Per-learner overage fee charged for attendance-active learners
     * beyond a plan's `max_learners` cap in a billing cycle — see
     * core/Billing/README.md ("usage-linked invoicing").
     */
    private const OVERAGE_RATE_PER_LEARNER = 15.00;

    public function run(): void
    {
        foreach (self::PLANS as $plan) {
            Plan::query()->firstOrCreate(
                ['key' => $plan['key']],
                [
                    'name' => $plan['name'],
                    'price' => $plan['price'],
                    'currency' => 'ZAR',
                    'billing_cycle' => BillingCycle::Monthly,
                    'max_learners' => $plan['max_learners'],
                    'max_staff' => $plan['max_staff'],
                    'ai_allowance' => $plan['ai_allowance'],
                    'overage_rate_per_learner' => self::OVERAGE_RATE_PER_LEARNER,
                    'is_active' => true,
                ],
            );
        }
    }
}
