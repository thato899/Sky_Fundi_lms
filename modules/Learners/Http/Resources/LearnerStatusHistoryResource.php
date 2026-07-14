<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Resources;

use Core\Users\Infrastructure\Models\User;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Learners\Domain\Enums\LearnerStatus;
use Modules\Learners\Infrastructure\Models\LearnerStatusHistory;

final class LearnerStatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $history = $this->resource;
        assert($history instanceof LearnerStatusHistory);
        $previous = $history->getAttribute('previous_status');
        $new = $history->getAttribute('new_status');
        $changedAt = $history->getAttribute('changed_at');
        assert($previous instanceof LearnerStatus && $new instanceof LearnerStatus && $changedAt instanceof DateTimeInterface);

        return [
            'previous_status' => $previous->value,
            'new_status' => $new->value,
            'reason' => $history->getAttribute('reason'),
            'actor' => $this->when($history->relationLoaded('actor'), function () use ($history): ?array {
                $actor = $history->getRelation('actor');

                return $actor instanceof User ? ['id' => $actor->getKey(), 'name' => $actor->getAttribute('name')] : null;
            }),
            'changed_at' => $changedAt->format(DATE_ATOM),
        ];
    }
}
