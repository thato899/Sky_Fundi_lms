<?php

declare(strict_types=1);

namespace Core\Security\Events;

use Core\Security\Infrastructure\Models\TrustedDevice;
use Core\Support\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TrustedDeviceAdded implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly TrustedDevice $device,
    ) {}

    public function auditAction(): string
    {
        return 'security.trusted_device_added';
    }

    public function auditTarget(): ?Model
    {
        return $this->device;
    }

    public function auditContext(): array
    {
        return ['after' => ['device_name' => $this->device->device_name, 'ip_address' => $this->device->ip_address]];
    }
}
