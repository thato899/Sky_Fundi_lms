<?php

declare(strict_types=1);

namespace Modules\Assessments\Domain\Enums;

enum AssessmentStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Finalized = 'finalized';
    case Cancelled = 'cancelled';
}
