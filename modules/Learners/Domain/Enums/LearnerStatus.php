<?php

declare(strict_types=1);

namespace Modules\Learners\Domain\Enums;

enum LearnerStatus: string
{
    case Pending = 'pending';
    case Admitted = 'admitted';
    case Active = 'active';
    case TemporarilyInactive = 'temporarily_inactive';
    case Suspended = 'suspended';
    case Withdrawn = 'withdrawn';
    case Transferred = 'transferred';
    case Completed = 'completed';
    case Archived = 'archived';
}
