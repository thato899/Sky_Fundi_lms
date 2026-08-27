<?php

declare(strict_types=1);

namespace Modules\Leaderboards\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Learners\Infrastructure\Models\LearnerProfile;

/**
 * Deliberately narrow: rank and a single aggregate value, nothing else.
 * There is no column here for a subject-by-subject mark to leak
 * through even by accident — see modules/Leaderboards/README.md.
 */
final class LeaderboardEntry extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['organization_id', 'leaderboard_id', 'learner_profile_id', 'rank', 'value'];

    protected function casts(): array
    {
        return ['rank' => 'integer', 'value' => 'decimal:2'];
    }

    public function leaderboard(): BelongsTo
    {
        return $this->belongsTo(Leaderboard::class);
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class, 'learner_profile_id');
    }
}
