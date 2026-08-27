<?php

declare(strict_types=1);

namespace Modules\Leaderboards\Domain\Enums;

/**
 * Private (default): only a learner's own entry is ever visible to them
 * or their guardians, and only leaderboards.manage holders see the full
 * ranked list. Published: the principal has made the full ranked list
 * visible to everyone in the leaderboard's scope. Neither state ever
 * changes what data an entry carries (see LeaderboardEntry) — only who
 * may see the full list versus just their own row.
 */
enum LeaderboardVisibility: string
{
    case Private = 'private';
    case Published = 'published';
}
