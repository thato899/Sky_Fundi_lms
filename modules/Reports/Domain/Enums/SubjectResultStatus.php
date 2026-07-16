<?php

declare(strict_types=1);

namespace Modules\Reports\Domain\Enums;

enum SubjectResultStatus: string
{
    case Calculated = 'calculated';
    case InsufficientData = 'insufficient_data';
    case Exempt = 'exempt';
    case NoResults = 'no_results';
}
