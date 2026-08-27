<?php

declare(strict_types=1);

namespace Modules\Leaderboards\Domain\Enums;

enum LeaderboardType: string
{
    case Academic = 'academic';
    case Sports = 'sports';
}
