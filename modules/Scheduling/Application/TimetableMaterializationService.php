<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application;

use Carbon\CarbonImmutable;
use Core\AuditLogs\Application\AuditLogService;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Scheduling\Domain\Enums\TemplateStatus;
use Modules\Scheduling\Infrastructure\Models\ScheduledLesson;
use Modules\Scheduling\Infrastructure\Models\TimetableTemplate;

final class TimetableMaterializationService
{
    public function __construct(private readonly LessonService $lessons, private readonly ScheduleConflictService $conflicts, private readonly AuditLogService $audit) {}

    /** @return array{created:int,skipped:int,conflicted:int,failed:int} */
    public function materialize(TimetableTemplate $template, Organization $organization, User $actor, string $from, string $to): array
    {
        if ($template->status !== TemplateStatus::Active) {
            throw new DomainException('Only active templates may be materialized.');
        }
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->startOfDay();
        if ($end->lessThan($start) || $start->diffInDays($end) > 93) {
            throw new DomainException('Materialization requires an ordered date range of at most 93 days.');
        }
        $start = $start->max($template->effective_start_date);
        $end = $end->min($template->effective_end_date);
        $result = ['created' => 0, 'skipped' => 0, 'conflicted' => 0, 'failed' => 0];
        for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
            foreach ($template->entries as $entry) {
                if ($date->dayOfWeekIso !== $entry->weekday) {
                    continue;
                }
                if (ScheduledLesson::query()->where('timetable_template_entry_id', $entry->getKey())->whereDate('lesson_date', $date)->exists()) {
                    $result['skipped']++;

                    continue;
                }
                $data = ['academic_year_id' => $template->academic_year_id, 'academic_term_id' => $template->academic_term_id, 'timetable_template_entry_id' => $entry->getKey(), 'grade_id' => $entry->grade_id, 'class_id' => $entry->class_id, 'subject_id' => $entry->subject_id, 'room_id' => $entry->room_id, 'delivery_mode' => $entry->delivery_mode->value, 'lesson_date' => $date->toDateString(), 'start_time' => $entry->start_time, 'end_time' => $entry->end_time];
                if ($this->conflicts->lesson((string) $organization->getKey(), [...$data, 'starts_at' => CarbonImmutable::parse($date->toDateString().' '.$entry->start_time, $organization->timezone)->utc(), 'ends_at' => CarbonImmutable::parse($date->toDateString().' '.$entry->end_time, $organization->timezone)->utc()])) {
                    $result['conflicted']++;

                    continue;
                }
                try {
                    $this->lessons->create($organization, $actor, $data);
                    $result['created']++;
                } catch (\Throwable) {
                    $result['failed']++;
                }
            }
        }
        $this->audit->record('scheduling.materialization_requested', $template, after: ['organization_id' => $organization->getKey(), 'from' => $from, 'to' => $to, ...$result]);

        return $result;
    }
}
