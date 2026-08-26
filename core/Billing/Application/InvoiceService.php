<?php

declare(strict_types=1);

namespace Core\Billing\Application;

use Carbon\CarbonInterface;
use Core\Billing\Domain\Enums\InvoiceLineType;
use Core\Billing\Domain\Enums\InvoiceStatus;
use Core\Billing\Events\InvoiceGenerated;
use Core\Billing\Events\InvoiceIssued;
use Core\Billing\Events\InvoicePaid;
use Core\Billing\Infrastructure\Models\Invoice;
use Core\Billing\Infrastructure\Models\Payment;
use Core\Subscriptions\Application\SubscriptionService;
use Core\Subscriptions\Domain\Enums\SubscriptionStatus;
use Core\Subscriptions\Infrastructure\Models\Plan;
use Core\Subscriptions\Infrastructure\Models\Subscription;
use Illuminate\Support\Facades\DB;

/**
 * Builds and settles usage-linked invoices — see core/Billing/README.md
 * and docs/adr/010-payfast-payment-gateway.md. Deliberately has no
 * knowledge of Attendance/Learners (Core never depends on a module,
 * per core/Subscriptions/README.md's "Allowed dependencies"); the
 * attendance-active-learner count is computed by the caller
 * (app/Console/Commands/GenerateInvoicesCommand) and passed in.
 */
final class InvoiceService
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    /**
     * Idempotent: calling this twice for the same subscription/period
     * returns the existing invoice rather than creating a duplicate —
     * the scheduled generator must be safe to rerun.
     */
    public function generateForPeriod(
        Subscription $subscription,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
        int $attendanceActiveLearnerCount,
    ): Invoice {
        // whereDate(), not where() — the `date` cast persists with a
        // time component (see Invoice::casts()), so a raw string
        // comparison against toDateString() would never match an
        // existing row and this lookup would silently stop being
        // idempotent.
        $existing = Invoice::query()
            ->where('subscription_id', $subscription->getKey())
            ->whereDate('billing_period_start', $periodStart->toDateString())
            ->whereDate('billing_period_end', $periodEnd->toDateString())
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $plan = Plan::findByKey((string) $subscription->plan);

        $invoice = DB::transaction(function () use ($subscription, $plan, $periodStart, $periodEnd, $attendanceActiveLearnerCount): Invoice {
            $invoice = Invoice::create([
                'organization_id' => $subscription->subscriber_id,
                'subscription_id' => $subscription->getKey(),
                'billing_period_start' => $periodStart->toDateString(),
                'billing_period_end' => $periodEnd->toDateString(),
                'status' => InvoiceStatus::Draft,
                'currency' => $plan?->currency ?? 'ZAR',
            ]);

            $baseAmount = (float) ($plan?->price ?? 0);
            $invoice->lineItems()->create([
                'line_type' => InvoiceLineType::SubscriptionBase,
                'description' => ($plan?->name ?? (string) $subscription->plan).' plan — base fee',
                'quantity' => 1,
                'unit_price' => $baseAmount,
                'amount' => $baseAmount,
            ]);

            $total = $baseAmount;
            $cap = $plan?->max_learners;
            $overageCount = $cap !== null ? max(0, $attendanceActiveLearnerCount - $cap) : 0;
            if ($overageCount > 0) {
                $rate = (float) ($plan?->overage_rate_per_learner ?: config('billing.default_overage_rate_per_learner'));
                $amount = $overageCount * $rate;
                $invoice->lineItems()->create([
                    'line_type' => InvoiceLineType::AttendanceOverage,
                    'description' => "{$overageCount} attendance-active learner(s) over the {$cap}-learner plan cap",
                    'quantity' => $overageCount,
                    'unit_price' => $rate,
                    'amount' => $amount,
                    'metadata' => ['attendance_active_learner_count' => $attendanceActiveLearnerCount, 'plan_cap' => $cap],
                ]);
                $total += $amount;
            }

            $invoice->update(['subtotal' => $total, 'total' => $total]);

            return $invoice->fresh('lineItems');
        });

        InvoiceGenerated::dispatch($invoice);

        return $invoice;
    }

    public function issue(Invoice $invoice): Invoice
    {
        $invoice->update([
            'status' => InvoiceStatus::Issued,
            'issued_at' => now(),
            'due_date' => now()->addDays((int) config('billing.invoice.due_days'))->toDateString(),
        ]);
        $invoice = $invoice->fresh();

        InvoiceIssued::dispatch($invoice);

        return $invoice;
    }

    public function markPaid(Invoice $invoice, Payment $payment): Invoice
    {
        $invoice->update([
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
            'external_reference' => $payment->gateway_reference,
        ]);
        $invoice = $invoice->fresh();

        if ($invoice->subscription !== null && $invoice->subscription->status === SubscriptionStatus::GracePeriod) {
            $this->subscriptions->reactivate($invoice->subscription);
        }

        InvoicePaid::dispatch($invoice);

        return $invoice;
    }

    /**
     * Dunning trigger: any Issued invoice past its due date moves to
     * Overdue and, if its subscription is still Active, enters the
     * subscription's existing grace-period mechanism — see
     * core/Subscriptions/README.md. Suspension after the grace period
     * remains Subscriptions' own scheduled sweep
     * (`platform:validate-subscriptions`); Billing only starts the
     * clock, it never suspends directly.
     */
    public function markOverdueAndGraceSubscriptions(): int
    {
        $overdue = Invoice::query()
            ->with('subscription')
            ->where('status', InvoiceStatus::Issued)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->get();

        foreach ($overdue as $invoice) {
            $invoice->update(['status' => InvoiceStatus::Overdue]);

            $subscription = $invoice->subscription;
            if ($subscription !== null && $subscription->status === SubscriptionStatus::Active) {
                $this->subscriptions->enterGracePeriod($subscription, now()->addDays(7));
            }
        }

        return $overdue->count();
    }
}
