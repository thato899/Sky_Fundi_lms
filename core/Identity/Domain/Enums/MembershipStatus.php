<?php

declare(strict_types=1);

namespace Core\Identity\Domain\Enums;

enum MembershipStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Suspended = 'suspended';
    case Rejected = 'rejected';
    case Expired = 'expired';
}
