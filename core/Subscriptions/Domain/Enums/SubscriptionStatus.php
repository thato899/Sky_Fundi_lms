<?php

declare(strict_types=1);

namespace Core\Subscriptions\Domain\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case GracePeriod = 'grace_period';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function isUsable(): bool
    {
        return in_array($this, [self::Active, self::GracePeriod], true);
    }
}
