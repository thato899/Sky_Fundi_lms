<?php

declare(strict_types=1);

namespace Core\Licensing\Events;

use Core\Licensing\Infrastructure\Models\License;
use Core\Support\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LicenseCancelled implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly License $license,
        public readonly array $context = [],
    ) {}

    public function auditAction(): string
    {
        return 'license.cancelled';
    }

    public function auditTarget(): ?Model
    {
        return $this->license;
    }

    public function auditContext(): array
    {
        return $this->context;
    }
}
