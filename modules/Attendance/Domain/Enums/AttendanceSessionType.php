<?php

declare(strict_types=1);

namespace Modules\Attendance\Domain\Enums;

enum AttendanceSessionType: string
{
    case ClassSession = 'class';
    case Subject = 'subject';
    case Homeroom = 'homeroom';
    case Examination = 'examination';
    case Extracurricular = 'extracurricular';
    case Other = 'other';
}
