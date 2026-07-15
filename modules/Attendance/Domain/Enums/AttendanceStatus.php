<?php

declare(strict_types=1);

namespace Modules\Attendance\Domain\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Excused = 'excused';
    case Remote = 'remote';
    case NotRecorded = 'not_recorded';
}
