<?php

declare(strict_types=1);

namespace Modules\Scheduling\Domain\Enums;

enum TemplateStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';
}
