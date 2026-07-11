<?php

declare(strict_types=1);

namespace Core\Users\Domain\Enums;

/**
 * The lifecycle status of a platform user, independent of any specific
 * module or tenant role. See core/Users/README.md.
 */
enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Locked = 'locked';
    case PendingVerification = 'pending_verification';
    case Deactivated = 'deactivated';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Locked => 'Locked',
            self::PendingVerification => 'Pending Verification',
            self::Deactivated => 'Deactivated',
        };
    }
}
