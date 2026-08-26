<?php

declare(strict_types=1);

namespace Core\Billing\Events;

use Core\Billing\Infrastructure\Models\Payment;
use Core\Support\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PaymentFailed implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Payment $payment,
    ) {}

    public function auditAction(): string
    {
        return 'billing.payment_failed';
    }

    public function auditTarget(): ?Model
    {
        return $this->payment;
    }

    public function auditContext(): array
    {
        return ['after' => ['status' => $this->payment->status->value]];
    }
}
