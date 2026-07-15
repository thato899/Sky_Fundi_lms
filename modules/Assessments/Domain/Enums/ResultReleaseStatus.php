<?php

declare(strict_types=1);

namespace Modules\Assessments\Domain\Enums;

enum ResultReleaseStatus: string
{
    case Withheld = 'withheld';
    case Released = 'released';
}
