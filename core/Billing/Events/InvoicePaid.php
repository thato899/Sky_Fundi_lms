<?php

declare(strict_types=1);

namespace Core\Billing\Events;

use Core\Billing\Infrastructure\Models\Invoice;
use Core\Support\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class InvoicePaid implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
    ) {}

    public function auditAction(): string
    {
        return 'billing.invoice_paid';
    }

    public function auditTarget(): ?Model
    {
        return $this->invoice;
    }

    public function auditContext(): array
    {
        return ['after' => ['paid_at' => $this->invoice->paid_at?->toIso8601String()]];
    }
}
