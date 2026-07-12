<?php

declare(strict_types=1);

namespace Core\Licensing\Domain\Enums;

enum LicenseStatus: string
{
    case PendingActivation = 'pending_activation';
    case Active = 'active';
    case Suspended = 'suspended';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function isUsable(): bool
    {
        return $this === self::Active;
    }
}
