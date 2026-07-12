<?php

declare(strict_types=1);

namespace Core\Security\Events;

use Core\Support\Contracts\Auditable;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A non-blocking security signal — informational today (recorded to
 * the audit trail via Auditable), reserved as the hook a future MFA
 * challenge or admin notification would subscribe to. See
 * core/Security/README.md.
 */
final class SecurityAlertRaised implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $reason,
        public readonly array $context = [],
    ) {}

    public function auditAction(): string
    {
        return 'security.alert_raised';
    }

    public function auditTarget(): ?Model
    {
        return $this->user;
    }

    public function auditContext(): array
    {
        return ['after' => array_merge(['reason' => $this->reason], $this->context)];
    }
}
