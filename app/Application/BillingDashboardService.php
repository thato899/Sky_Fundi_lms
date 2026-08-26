<?php

declare(strict_types=1);

namespace App\Application;

use Core\Billing\Infrastructure\Models\Invoice;
use Core\Subscriptions\Infrastructure\Models\Plan;
use Core\Subscriptions\Infrastructure\Models\Subscription;
use Modules\Organizations\Infrastructure\Models\Organization;

/**
 * Aggregates real billing state for the org-admin `/billing` page —
 * mirrors HackathonSubscriptionService's role for the older demo
 * `/subscription` page, but reads Core\Billing/Core\Subscriptions
 * directly instead of config('hackathon.*'). See core/Billing/README.md.
 */
final class BillingDashboardService
{
    public function for(Organization $organization): array
    {
        $subscription = Subscription::query()
            ->where('subscriber_type', Organization::class)
            ->where('subscriber_id', $organization->getKey())
            ->latest()
            ->first();

        return [
            'subscription' => $subscription,
            'plans' => Plan::query()->where('is_active', true)->orderBy('price')->get(),
            'invoices' => Invoice::query()
                ->where('organization_id', $organization->getKey())
                ->with('lineItems')
                ->latest('billing_period_start')
                ->limit(24)
                ->get(),
        ];
    }
}
