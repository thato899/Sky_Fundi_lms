<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application;

use Core\AuditLogs\Application\AuditLogService;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Support\Arr;
use Modules\Academics\Infrastructure\Models\AcademicTerm;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\TimetablePeriod;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Scheduling\Domain\Enums\TemplateStatus;
use Modules\Scheduling\Infrastructure\Models\Room;
use Modules\Scheduling\Infrastructure\Models\TimetableTemplate;
use Modules\Scheduling\Infrastructure\Models\TimetableTemplateEntry;

final class TimetableService
{
    public function __construct(private readonly ScheduleConflictService $conflicts, private readonly AuditLogService $audit) {}

    public function create(Organization $organization, User $actor, array $data): TimetableTemplate
    {
        /** @var AcademicYear|null $year */
        $year = AcademicYear::query()->where('organization_id', $organization->getKey())->find($data['academic_year_id'] ?? null);
        if (! $year) {
            throw new DomainException('The academic year must belong to the active organization.');
        }
        if (($data['effective_start_date'] ?? '') > ($data['effective_end_date'] ?? '')) {
            throw new DomainException('Template start must be on or before its end.');
        }
        if ($data['effective_start_date'] < $year->start_date->toDateString() || $data['effective_end_date'] > $year->end_date->toDateString()) {
            throw new DomainException('Template dates must fall within the academic year.');
        }
        if ($data['academic_term_id'] ?? null) {
            $term = AcademicTerm::query()->where('organization_id', $organization->getKey())->where('academic_year_id', $year->getKey())->find($data['academic_term_id']);
            if (! $term) {
                throw new DomainException('The term must belong to the selected year.');
            }
        }
        $template = TimetableTemplate::query()->create([...Arr::only($data, ['academic_year_id', 'academic_term_id', 'name', 'description', 'effective_start_date', 'effective_end_date']), 'organization_id' => $organization->getKey(), 'status' => TemplateStatus::Draft, 'created_by' => $actor->getKey(), 'updated_by' => $actor->getKey()]);
        $this->audit->record('scheduling.template_created', $template, after: ['organization_id' => $organization->getKey()]);

        return $template;
    }

    public function addEntry(TimetableTemplate $template, array $data): TimetableTemplateEntry
    {
        if ($template->status === TemplateStatus::Archived) {
            throw new DomainException('Archived templates are read-only.');
        }
        if (($data['weekday'] ?? 0) < 1 || $data['weekday'] > 7) {
            throw new DomainException('Weekday must be between 1 and 7.');
        }
        /** @var ClassGroup|null $class */
        $class = ClassGroup::query()->where('organization_id', $template->organization_id)->find($data['class_id'] ?? null);
        if (! $class || $class->grade_id !== ($data['grade_id'] ?? null)) {
            throw new DomainException('The class must belong to the selected grade and organization.');
        }
        if ($data['teaching_period_id'] ?? null) {
            /** @var TimetablePeriod|null $period */
            $period = TimetablePeriod::query()->where('organization_id', $template->organization_id)->find($data['teaching_period_id']);
            if (! $period || $period->is_break || $period->status->value !== 'active') {
                throw new DomainException('An active non-break teaching period is required.');
            } $data['start_time'] = $period->start_time;
            $data['end_time'] = $period->end_time;
        }
        if (($data['start_time'] ?? '') >= ($data['end_time'] ?? '')) {
            throw new DomainException('Entry end must be after its start.');
        }
        if ($data['room_id'] ?? null) {
            /** @var Room|null $room */
            $room = Room::query()->where('organization_id', $template->organization_id)->find($data['room_id']);
            if (! $room?->is_active) {
                throw new DomainException('An active organization room is required.');
            }
        }
        if ($this->conflicts->templateEntry((string) $template->organization_id, $data)) {
            throw new DomainException('The template entry has a scheduling conflict.');
        }

        return TimetableTemplateEntry::query()->create([...Arr::only($data, ['weekday', 'teaching_period_id', 'start_time', 'end_time', 'grade_id', 'class_id', 'subject_id', 'room_id', 'delivery_mode', 'status', 'notes', 'display_order']), 'organization_id' => $template->organization_id, 'timetable_template_id' => $template->getKey()]);
    }

    public function activate(TimetableTemplate $template, User $actor): TimetableTemplate
    {
        if ($template->entries()->doesntExist()) {
            throw new DomainException('A template needs at least one entry before activation.');
        }
        foreach ($template->entries as $entry) {
            if ($this->conflicts->templateEntry((string) $template->organization_id, $entry->getAttributes(), (string) $entry->getKey())) {
                throw new DomainException('Template conflicts must be resolved before activation.');
            }
        }
        $competing = TimetableTemplate::query()->where('organization_id', $template->organization_id)->whereKeyNot($template->getKey())->where('status', 'active')->where('effective_start_date', '<=', $template->effective_end_date)->where('effective_end_date', '>=', $template->effective_start_date)->exists();
        if ($competing) {
            throw new DomainException('An overlapping active template already exists.');
        }
        $template->update(['status' => TemplateStatus::Active, 'is_active' => true, 'updated_by' => $actor->getKey()]);
        $this->audit->record('scheduling.template_activated', $template, after: ['organization_id' => $template->organization_id]);

        return $template->refresh();
    }
}
