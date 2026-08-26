<?php

declare(strict_types=1);

namespace App\Listeners;

use Core\Billing\Events\InvoiceIssued;
use Core\Notifications\Application\NotificationService;
use Modules\Learners\Infrastructure\Models\LearnerGuardianRelationship;

/**
 * Notifies guardians marked `receives_financial_communication` when a
 * new invoice is issued for their organization — the guardian-facing
 * counterpart to Core\Billing\Listeners\NotifyOnInvoiceIssued (which
 * notifies the organization's administrators). Lives in app/, not
 * core/Billing, because it crosses into the Learners module for
 * guardian relationship data and Core never depends on a module — see
 * core/Billing/README.md. Mirrors
 * Modules\Reports\Application\ReportCardService's guardian
 * notification pattern (notify each linked, active, financially-
 * communicating guardian's user, once per guardian).
 */
final class NotifyGuardiansOnInvoiceIssued
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(InvoiceIssued $event): void
    {
        $invoice = $event->invoice;
        $data = ['message' => 'A new invoice is ready for your school.', 'total' => (string) $invoice->total, 'due_date' => $invoice->due_date?->toDateString()];

        $guardians = LearnerGuardianRelationship::query()
            ->with('guardian.user')
            ->where('organization_id', $invoice->organization_id)
            ->where('status', 'active')
            ->where('receives_financial_communication', true)
            ->whereRaw('(effective_from is null or effective_from <= ?)', [today()->toDateString()])
            ->whereRaw('(effective_until is null or effective_until >= ?)', [today()->toDateString()])
            ->get()
            ->pluck('guardian')
            ->filter(fn ($guardian) => $guardian !== null && $guardian->user !== null && $guardian->status->value === 'active' && $guardian->archived_at === null && $guardian->deleted_at === null)
            ->unique('user_id');

        foreach ($guardians as $guardian) {
            $this->notifications->send($guardian->user, 'billing.invoice_issued', $data);
        }
    }
}
