<?php

declare(strict_types=1);

namespace Modules\Attendance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Attendance\Infrastructure\Models\AttendanceSession;

final class AttendanceSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var AttendanceSession $session */
        $session = $this->resource;
        $class = $session->relationLoaded('classGroup') ? $session->getRelation('classGroup') : null;
        $subject = $session->relationLoaded('subject') ? $session->getRelation('subject') : null;
        $entries = $session->relationLoaded('entries') ? $session->getRelation('entries') : null;

        return ['uuid' => $session->getAttribute('uuid'), 'date' => $session->getAttribute('session_date')?->toDateString(), 'session_type' => $session->getAttribute('session_type')?->value, 'title' => $session->getAttribute('title'), 'status' => $session->getAttribute('status')?->value, 'start_time' => $session->getAttribute('start_time'), 'end_time' => $session->getAttribute('end_time'), 'finalized_at' => $session->getAttribute('finalized_at')?->toIso8601String(), 'class' => $class ? ['id' => $class->getKey(), 'name' => $class->getAttribute('name')] : null, 'subject' => $subject ? ['id' => $subject->getKey(), 'name' => $subject->getAttribute('name')] : null, 'entries' => $entries?->map(function ($entry): array {
            $learner = $entry->getRelation('learner');

            return ['uuid' => $entry->getAttribute('uuid'), 'learner_uuid' => $learner->getAttribute('uuid'), 'learner_number' => $learner->getAttribute('learner_number'), 'learner_name' => trim($learner->getAttribute('first_name').' '.$learner->getAttribute('last_name')), 'status' => $entry->getAttribute('status')->value, 'arrival_time' => $entry->getAttribute('arrival_time'), 'minutes_late' => $entry->getAttribute('minutes_late'), 'reason' => $entry->getAttribute('reason')];
        })];
    }
}
