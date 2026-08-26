<?php

declare(strict_types=1);

namespace Core\Billing\Listeners;

use Core\Billing\Events\PaymentFailed;
use Core\Notifications\Application\NotificationService;
use Core\Users\Infrastructure\Models\User;
use Modules\Organizations\Infrastructure\Models\Organization;

/**
 * Dunning starts here: an org's administrators are told immediately
 * that a payment attempt failed, ahead of the scheduled overdue/grace
 * sweep in Application\InvoiceService::markOverdueAndGraceSubscriptions().
 */
final class NotifyOnPaymentFailed
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(PaymentFailed $event): void
    {
        $organization = Organization::query()->find($event->payment->organization_id);
        if ($organization === null) {
            return;
        }

        $data = ['message' => 'A Sky Fundi payment attempt failed.', 'amount' => (string) $event->payment->amount, 'purpose' => $event->payment->purpose->value];
        foreach ($organization->administrators()->get() as $admin) {
            if ($admin instanceof User) {
                $this->notifications->send($admin, 'billing.payment_failed', $data);
            }
        }
    }
}
