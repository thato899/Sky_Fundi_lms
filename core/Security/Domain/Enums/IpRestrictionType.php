<?php

declare(strict_types=1);

namespace Core\Security\Domain\Enums;

enum IpRestrictionType: string
{
    case Allow = 'allow';
    case Deny = 'deny';
}
