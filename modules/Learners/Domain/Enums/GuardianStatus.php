<?php

declare(strict_types=1);

namespace Modules\Learners\Domain\Enums;

enum GuardianStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';
}
