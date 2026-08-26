<?php

declare(strict_types=1);

namespace Core\Billing\Domain\Enums;

enum InvoiceLineType: string
{
    case SubscriptionBase = 'subscription_base';
    case AttendanceOverage = 'attendance_overage';
    case Adjustment = 'adjustment';
}
