<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\Enums;

enum AcademicTermStatus: string
{
    case Upcoming = 'upcoming';
    case Current = 'current';
    case Closed = 'closed';
}
