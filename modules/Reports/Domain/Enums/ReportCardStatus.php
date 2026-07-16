<?php

declare(strict_types=1);

namespace Modules\Reports\Domain\Enums;

enum ReportCardStatus: string
{
    case Draft = 'draft';
    case Generated = 'generated';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Published = 'published';
    case Withdrawn = 'withdrawn';
}
