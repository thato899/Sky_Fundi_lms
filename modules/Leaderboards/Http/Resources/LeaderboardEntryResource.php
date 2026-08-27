<?php

declare(strict_types=1);

namespace Modules\Leaderboards\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Leaderboards\Infrastructure\Models\LeaderboardEntry;

/**
 * Deliberately the only shape a leaderboard entry is ever serialized
 * as: rank, the single aggregate value, and (only when the caller is
 * allowed to see the full list) the learner's display name. Never a
 * subject-by-subject mark, never anything else off LearnerProfile.
 */
final class LeaderboardEntryResource extends JsonResource
{
    public function __construct(private readonly LeaderboardEntry $entryModel, private readonly bool $showIdentity)
    {
        parent::__construct($entryModel);
    }

    public function toArray(Request $request): array
    {
        return [
            'rank' => $this->entryModel->getAttribute('rank'),
            'value' => (float) $this->entryModel->getAttribute('value'),
            'learner_name' => $this->showIdentity
                ? trim($this->entryModel->learner->getAttribute('first_name').' '.$this->entryModel->learner->getAttribute('last_name'))
                : null,
            'is_you' => $this->when($request->attributes->has('leaderboard_viewer_learner_ids'), fn () => in_array(
                $this->entryModel->getAttribute('learner_profile_id'),
                $request->attributes->get('leaderboard_viewer_learner_ids', []),
                true,
            )),
        ];
    }
}
