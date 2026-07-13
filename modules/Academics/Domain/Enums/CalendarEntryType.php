<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\Enums;

enum CalendarEntryType: string
{
    case SchoolDay = 'school_day';
    case PublicHoliday = 'public_holiday';
    case ExamPeriod = 'exam_period';
    case AssessmentPeriod = 'assessment_period';
    case Event = 'event';
}
