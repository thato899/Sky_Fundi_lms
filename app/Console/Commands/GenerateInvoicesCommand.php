<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\CarbonInterface;
use Core\Billing\Application\InvoiceService;
use Core\Billing\Domain\Enums\InvoiceStatus;
use Core\Subscriptions\Domain\Enums\BillingCycle;
use Core\Subscriptions\Domain\Enums\SubscriptionStatus;
use Core\Subscriptions\Infrastructure\Models\Subscription;
use Illuminate\Console\Command;
use Modules\Attendance\Domain\Enums\AttendanceSessionStatus;
use Modules\Attendance\Infrastructure\Models\AttendanceEntry;
use Modules\Organizations\Infrastructure\Models\Organization;

/**
 * `php artisan platform:generate-invoices` — generates and issues this
 * cycle's invoice for every Active/GracePeriod subscription, from real
 * Attendance-derived usage. Lives in app/ rather than
 * core/Billing or core/Scheduler because building the usage figure
 * requires reading the Attendance module, and Core services never
 * depend on a module (see core/Billing/README.md and
 * core/Subscriptions/README.md's "Allowed dependencies"). Scheduled
 * monthly — see Core\Scheduler\Providers\SchedulerServiceProvider.
 */
final class GenerateInvoicesCommand extends Command
{
    protected $signature = 'platform:generate-invoices';

    protected $description = "Generate and issue this cycle's usage-linked invoices.";

    public function handle(InvoiceService $invoices): int
    {
        $subscriptions = Subscription::query()
            ->where('subscriber_type', Organization::class)
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::GracePeriod])
            ->get();

        $generated = 0;
        foreach ($subscriptions as $subscription) {
            [$periodStart, $periodEnd] = $this->periodFor($subscription->billing_cycle);
            $activeLearnerCount = $this->attendanceActiveLearnerCount((string) $subscription->subscriber_id, $periodStart, $periodEnd);

            $invoice = $invoices->generateForPeriod($subscription, $periodStart, $periodEnd, $activeLearnerCount);
            if ($invoice->status === InvoiceStatus::Draft) {
                $invoices->issue($invoice);
                $generated++;
            }
        }

        $this->info("{$generated} invoice(s) generated and issued.");

        return self::SUCCESS;
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function periodFor(BillingCycle $cycle): array
    {
        return match ($cycle) {
            BillingCycle::Annual => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    private function attendanceActiveLearnerCount(string $organizationId, CarbonInterface $periodStart, CarbonInterface $periodEnd): int
    {
        return AttendanceEntry::query()
            ->where('organization_id', $organizationId)
            // whereDate() twice, not whereBetween() — session_date's
            // `date` cast persists with a time component, so a plain
            // whereBetween() against toDateString() bounds would wrongly
            // exclude sessions on the period's last day (see
            // Core\Billing\Application\InvoiceService's identical fix).
            ->whereHas('session', fn ($query) => $query
                ->where('status', AttendanceSessionStatus::Finalized)
                ->whereDate('session_date', '>=', $periodStart->toDateString())
                ->whereDate('session_date', '<=', $periodEnd->toDateString()))
            ->distinct('learner_profile_id')
            ->count('learner_profile_id');
    }
}
