<?php

declare(strict_types=1);

namespace Modules\Materials\Domain\Enums;

enum MaterialStatus: string
{
    case Pending = 'pending';
    case Chunking = 'chunking';
    case Ready = 'ready';
    case Failed = 'failed';
}
