<?php

declare(strict_types=1);

namespace Modules\Organizations\Domain\Enums;

enum OrganizationStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Inactive = 'inactive';
}
