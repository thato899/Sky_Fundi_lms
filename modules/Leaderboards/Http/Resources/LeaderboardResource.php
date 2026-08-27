<?php

declare(strict_types=1);

namespace Modules\Leaderboards\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Leaderboards\Infrastructure\Models\Leaderboard;

final class LeaderboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $leaderboard = $this->resource;
        assert($leaderboard instanceof Leaderboard);

        return [
            'id' => $leaderboard->getKey(),
            'type' => $leaderboard->getAttribute('type')->value,
            'name' => $leaderboard->getAttribute('name'),
            'subject_name' => $leaderboard->subject?->getAttribute('name'),
            'grade_name' => $leaderboard->grade?->getAttribute('name'),
            'class_name' => $leaderboard->classGroup?->getAttribute('name'),
            'visibility' => $leaderboard->getAttribute('visibility')->value,
            'generated_at' => $leaderboard->getAttribute('generated_at')?->toIso8601String(),
            'published_at' => $leaderboard->getAttribute('published_at')?->toIso8601String(),
        ];
    }
}
