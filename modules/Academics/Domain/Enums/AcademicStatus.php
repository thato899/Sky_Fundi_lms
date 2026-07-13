<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\Enums;

/**
 * Shared status for the simpler catalog-style entities (Grade, Class,
 * Subject, Department, Curriculum, TimetablePeriod) that don't need
 * AcademicYear's richer lifecycle — see
 * modules/Academics/README.md.
 */
enum AcademicStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';
}
