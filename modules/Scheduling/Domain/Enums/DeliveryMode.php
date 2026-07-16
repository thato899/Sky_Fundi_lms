<?php

declare(strict_types=1);

namespace Modules\Scheduling\Domain\Enums;

enum DeliveryMode: string
{
    case InPerson = 'in_person';
    case Online = 'online';
    case Hybrid = 'hybrid';
}
