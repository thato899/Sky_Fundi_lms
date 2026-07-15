<?php

declare(strict_types=1);

namespace Modules\Assessments\Domain\Enums;

enum AssessmentResultStatus: string
{
    case Pending = 'pending';
    case Marked = 'marked';
    case Absent = 'absent';
    case Excused = 'excused';
    case Exempt = 'exempt';
    case NotSubmitted = 'not_submitted';
}
