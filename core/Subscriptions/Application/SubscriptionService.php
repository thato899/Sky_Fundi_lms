<?php

declare(strict_types=1);

namespace Core\Subscriptions\Application;

use Core\AuditLogs\Application\AuditLogService;
use Core\Subscriptions\Domain\Enums\SubscriptionStatus;
use Core\Subscriptions\Events\SubscriptionCancelled;
use Core\Subscriptions\Events\SubscriptionEnteredGracePeriod;
use Core\Subscriptions\Events\SubscriptionReactivated;
use Core\Subscriptions\Events\SubscriptionRenewed;
use Core\Subscriptions\Events\SubscriptionStarted;
use Core\Subscriptions\Events\SubscriptionSuspended;
use Core\Subscriptions\Infrastructure\Models\Subscription;
use Illuminate\Database\Eloquent\Collection;

/**
 * The only place a Subscription is created or transitioned — see
 * core/Subscriptions/README.md.
 */
final class SubscriptionService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function start(array $attributes): Subscription
    {
        $subscription = Subscription::create(array_merge($attributes, [
            'status' => SubscriptionStatus::Active,
            'started_at' => $attributes['started_at'] ?? now()->toDateString(),
        ]));

        event(new SubscriptionStarted($subscription));

        return $subscription;
    }

    public function renew(Subscription $subscription, \DateTimeInterface $renewalDate): Subscription
    {
        $subscription->update([
            'renewal_date' => $renewalDate,
            'status' => SubscriptionStatus::Active,
            'grace_period_ends_at' => null,
        ]);

        event(new SubscriptionRenewed($subscription));

        return $subscription->fresh();
    }

    public function enterGracePeriod(Subscription $subscription, \DateTimeInterface $gracePeriodEndsAt): Subscription
    {
        $subscription->update([
            'status' => SubscriptionStatus::GracePeriod,
            'grace_period_ends_at' => $gracePeriodEndsAt,
        ]);

        event(new SubscriptionEnteredGracePeriod($subscription));

        return $subscription->fresh();
    }

    public function suspend(Subscription $subscription): Subscription
    {
        $subscription->update(['status' => SubscriptionStatus::Suspended]);

        event(new SubscriptionSuspended($subscription));

        return $subscription->fresh();
    }

    public function reactivate(Subscription $subscription): Subscription
    {
        $subscription->update(['status' => SubscriptionStatus::Active, 'grace_period_ends_at' => null]);

        event(new SubscriptionReactivated($subscription));

        return $subscription->fresh();
    }

    public function cancel(Subscription $subscription): Subscription
    {
        $subscription->update(['status' => SubscriptionStatus::Cancelled]);

        event(new SubscriptionCancelled($subscription));

        return $subscription->fresh();
    }

    /**
     * Live usage tracking against the subscription's own limits — see
     * core/Subscriptions/README.md. Does not itself enforce the
     * limit; callers check `isOverUserLimit()`/`isOverStorageLimit()`
     * and decide what to do (e.g. block a new user invite).
     */
    public function recordUsage(Subscription $subscription, array $usage): Subscription
    {
        $subscription->update(array_intersect_key($usage, array_flip([
            'current_users', 'current_storage_mb', 'ai_usage',
        ])));

        return $subscription->fresh();
    }

    /**
     * Any subscription past its grace period is moved to Suspended.
     * Called by the scheduled `platform:validate-subscriptions`
     * command — see core/Scheduler/Console/ValidateSubscriptionsCommand.php.
     */
    public function suspendOverdueGracePeriods(): int
    {
        $overdue = Subscription::query()
            ->where('status', SubscriptionStatus::GracePeriod)
            ->whereNotNull('grace_period_ends_at')
            ->where('grace_period_ends_at', '<', now()->toDateString())
            ->get();

        foreach ($overdue as $subscription) {
            $this->suspend($subscription);
        }

        return $overdue->count();
    }

    /**
     * A subscription's history is the same searchable audit trail
     * every other Core action uses — see docs/security/README.md and
     * Core\AuditLogs\Application\AuditLogService::search(). Kept here
     * as a convenience method instead of a bespoke history table, per
     * the project's "no duplicated logic" rule.
     */
    public function history(Subscription $subscription): Collection
    {
        return $this->auditLog->search([
            'target_type' => $subscription->getMorphClass(),
            'target_id' => $subscription->getKey(),
        ])->get();
    }
}
