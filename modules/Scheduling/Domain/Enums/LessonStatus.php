<?php

declare(strict_types=1);

namespace Modules\Scheduling\Domain\Enums;

enum LessonStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Rescheduled = 'rescheduled';
    case Missed = 'missed';
}
