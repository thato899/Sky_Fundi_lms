<?php

declare(strict_types=1);

namespace Core\Billing\Listeners;

use Core\Billing\Events\InvoiceIssued;
use Core\Notifications\Application\NotificationService;
use Core\Users\Infrastructure\Models\User;
use Modules\Organizations\Infrastructure\Models\Organization;

/**
 * Notifies an organization's administrators when a new billing-cycle
 * invoice is issued. Guardian notification (for guardians marked
 * `receives_financial_communication`) is handled separately in
 * app/Listeners — see that class's docblock for why it can't live
 * here (Core never depends on the Learners module).
 */
final class NotifyOnInvoiceIssued
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(InvoiceIssued $event): void
    {
        $organization = Organization::query()->find($event->invoice->organization_id);
        if ($organization === null) {
            return;
        }

        $data = ['message' => 'A new Sky Fundi invoice is ready.', 'total' => (string) $event->invoice->total, 'due_date' => $event->invoice->due_date?->toDateString()];
        foreach ($organization->administrators()->get() as $admin) {
            if ($admin instanceof User) {
                $this->notifications->send($admin, 'billing.invoice_issued', $data);
            }
        }
    }
}
