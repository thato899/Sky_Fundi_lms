<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\Enums;

/**
 * See modules/Academics/README.md#academic-year. Exactly one
 * AcademicYear may be Current at a time — enforced by
 * Application\AcademicYearService::setCurrent(), not by this enum.
 */
enum AcademicYearStatus: string
{
    case Upcoming = 'upcoming';
    case Current = 'current';
    case Closed = 'closed';
    case Archived = 'archived';
}
