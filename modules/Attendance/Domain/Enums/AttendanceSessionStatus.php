<?php

declare(strict_types=1);

namespace Modules\Attendance\Domain\Enums;

enum AttendanceSessionStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Finalized = 'finalized';
    case Cancelled = 'cancelled';
}
