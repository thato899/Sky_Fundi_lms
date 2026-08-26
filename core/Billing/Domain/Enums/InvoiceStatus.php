<?php

declare(strict_types=1);

namespace Core\Billing\Domain\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Void = 'void';
}
