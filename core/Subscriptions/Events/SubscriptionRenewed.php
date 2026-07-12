<?php

declare(strict_types=1);

namespace Core\Subscriptions\Events;

use Core\Subscriptions\Infrastructure\Models\Subscription;
use Core\Support\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SubscriptionRenewed implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly array $context = [],
    ) {}

    public function auditAction(): string
    {
        return 'subscription.renewed';
    }

    public function auditTarget(): ?Model
    {
        return $this->subscription;
    }

    public function auditContext(): array
    {
        return $this->context;
    }
}
