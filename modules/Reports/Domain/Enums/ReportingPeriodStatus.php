<?php

declare(strict_types=1);

namespace Modules\Reports\Domain\Enums;

enum ReportingPeriodStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';
    case Archived = 'archived';
}
