<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application;

use Illuminate\Database\Eloquent\Model;
use Modules\Academics\Infrastructure\Models\CalendarEntry;
use Modules\Scheduling\Infrastructure\Models\ScheduledLesson;
use Modules\Scheduling\Infrastructure\Models\TimetableTemplateEntry;

final class ScheduleConflictService
{
    /** @return array<int,array{type:string,id:string,message:string}> */
    public function lesson(string $organizationId, array $proposal, ?string $excludeId = null): array
    {
        $query = ScheduledLesson::query();
        $query->where('organization_id', $organizationId)->whereDate('lesson_date', $proposal['lesson_date'])
            ->whereNotIn('status', ['cancelled', 'rescheduled'])->where('starts_at', '<', $proposal['ends_at'])->where('ends_at', '>', $proposal['starts_at']);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        $conflicts = collect();
        foreach ($query->get() as $existing) {
            foreach (['class_id' => 'class', 'room_id' => 'room'] as $field => $type) {
                if (($proposal[$field] ?? null) && $existing->getAttribute($field) === $proposal[$field]) {
                    $conflicts->push($this->detail($type, $existing));
                }
            }
            $staffIds = $proposal['staff_ids'] ?? [];
            if ($staffIds && $existing->staff()->whereIn('staff_profiles.id', $staffIds)->exists()) {
                $conflicts->push($this->detail('staff', $existing));
            }
        }
        $closures = CalendarEntry::query()->where('organization_id', $organizationId)->where('affects_teaching', true)->where('status', 'active')
            ->whereDate('start_date', '<=', $proposal['lesson_date'])->whereDate('end_date', '>=', $proposal['lesson_date'])
            ->where(function ($q) use ($proposal): void {
                $q->where('closure_scope', 'organization')->orWhere(fn ($x) => $x->where('closure_scope', 'grade')->where('grade_id', $proposal['grade_id']))->orWhere(fn ($x) => $x->where('closure_scope', 'class')->where('class_id', $proposal['class_id']));
            })->get();
        foreach ($closures as $closure) {
            /** @var CalendarEntry $closure */
            $conflicts->push(['type' => 'closure', 'id' => (string) $closure->getKey(), 'message' => 'Teaching is closed by '.$closure->getAttribute('name').'.']);
        }

        return $conflicts->unique(fn ($c) => $c['type'].$c['id'])->values()->all();
    }

    /** @return array<int,array{type:string,id:string,message:string}> */
    public function templateEntry(string $organizationId, array $proposal, ?string $excludeId = null): array
    {
        return TimetableTemplateEntry::query()->where('organization_id', $organizationId)->where('weekday', $proposal['weekday'])->where('status', 'active')
            ->where('start_time', '<', $proposal['end_time'])->where('end_time', '>', $proposal['start_time'])->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))->get()
            ->filter(fn ($e) => $e->class_id === ($proposal['class_id'] ?? null) || (($proposal['room_id'] ?? null) && $e->room_id === $proposal['room_id']))
            ->map(fn ($e) => $this->detail($e->class_id === ($proposal['class_id'] ?? null) ? 'class' : 'room', $e))->values()->all();
    }

    private function detail(string $type, Model $record): array
    {
        return ['type' => $type, 'id' => (string) $record->getKey(), 'message' => ucfirst($type).' is already scheduled during this time.'];
    }
}
