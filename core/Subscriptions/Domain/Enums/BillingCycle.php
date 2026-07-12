<?php

declare(strict_types=1);

namespace Core\Subscriptions\Domain\Enums;

enum BillingCycle: string
{
    case Monthly = 'monthly';
    case Annual = 'annual';
    case Lifetime = 'lifetime';
    case Custom = 'custom';
}
