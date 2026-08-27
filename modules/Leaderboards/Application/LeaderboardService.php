<?php

declare(strict_types=1);

namespace Modules\Leaderboards\Application;

use Core\AuditLogs\Application\AuditLogService;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Leaderboards\Domain\Enums\LeaderboardType;
use Modules\Leaderboards\Domain\Enums\LeaderboardVisibility;
use Modules\Leaderboards\Infrastructure\Models\Leaderboard;
use Modules\Leaderboards\Infrastructure\Models\LeaderboardEntry;
use Modules\Leaderboards\Infrastructure\Models\SportsRecord;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Reports\Infrastructure\Models\ReportCard;
use Modules\Reports\Infrastructure\Models\ReportingPeriod;

/**
 * Generates and manages leaderboard standings. See
 * modules/Leaderboards/README.md for the visibility contract this
 * exists to protect: an entry carries only a rank and one aggregate
 * value, and a learner (or their guardian) may always see their own
 * entry but never another learner's until the leaderboard is
 * published.
 *
 * Academic standings are computed from already-approved ReportCard /
 * ReportCardSubjectResult snapshots — the same numbers a family
 * already sees on a published report card — rather than re-deriving
 * an average independently, so a leaderboard can never disagree with
 * the report card it was built from. Sports standings sum
 * SportsRecord points logged by staff (see SportsRecordService); there
 * is no equivalent official record to defer to, since this platform
 * has no sports module of its own.
 */
final class LeaderboardService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * @param  array{reporting_period_id: string, subject_id?: string|null, grade_id?: string|null, class_id?: string|null, name?: string|null}  $data
     */
    public function generateAcademic(Organization $organization, User $actor, array $data): Leaderboard
    {
        $period = ReportingPeriod::query()->where('organization_id', $organization->getKey())->find($data['reporting_period_id'] ?? null);
        if (! $period instanceof ReportingPeriod) {
            throw new DomainException('The reporting period must belong to the active organization.');
        }
        $subject = $this->owned(Subject::class, $data['subject_id'] ?? null, $organization);
        $grade = $this->owned(Grade::class, $data['grade_id'] ?? null, $organization);
        $class = $this->owned(ClassGroup::class, $data['class_id'] ?? null, $organization);

        $cards = ReportCard::query()
            ->where('organization_id', $organization->getKey())
            ->where('reporting_period_id', $period->getKey())
            ->whereIn('status', ['approved', 'published'])
            ->when($grade, fn ($query) => $query->where('grade_id', $grade->getKey()))
            ->when($class, fn ($query) => $query->where('class_id', $class->getKey()))
            ->when($subject, fn ($query) => $query->with(['subjects' => fn ($q) => $q->where('subject_id', $subject->getKey())->where('subject_result_status', 'calculated')]))
            ->orderByDesc('version_number')
            ->get()
            ->unique('learner_profile_id'); // latest version per learner, ordered desc above

        $rows = $subject
            ? $cards->map(fn (ReportCard $card) => ['learner_profile_id' => $card->learner_profile_id, 'value' => $card->subjects->first()?->calculated_percentage])->filter(fn ($row) => $row['value'] !== null)
            : $cards->whereNotNull('overall_average')->map(fn (ReportCard $card) => ['learner_profile_id' => $card->learner_profile_id, 'value' => (float) $card->overall_average]);

        if ($rows->isEmpty()) {
            throw new DomainException('No eligible, approved report cards were found for this period and scope — generate and approve report cards first.');
        }

        $name = trim((string) ($data['name'] ?? '')) ?: $this->defaultAcademicName($period, $subject, $grade, $class);

        return $this->save($organization, $actor, LeaderboardType::Academic, $name, $rows, [
            'subject_id' => $subject?->getKey(), 'grade_id' => $grade?->getKey(), 'class_id' => $class?->getKey(),
            'reporting_period_id' => $period->getKey(), 'period_start_date' => null, 'period_end_date' => null,
        ]);
    }

    /**
     * @param  array{period_start_date: string, period_end_date: string, grade_id?: string|null, class_id?: string|null, name?: string|null}  $data
     */
    public function generateSports(Organization $organization, User $actor, array $data): Leaderboard
    {
        $start = $data['period_start_date'] ?? null;
        $end = $data['period_end_date'] ?? null;
        if (! is_string($start) || ! is_string($end) || $end < $start) {
            throw new DomainException('A valid start and end date are required.');
        }
        $grade = $this->owned(Grade::class, $data['grade_id'] ?? null, $organization);
        $class = $this->owned(ClassGroup::class, $data['class_id'] ?? null, $organization);

        $totals = SportsRecord::query()
            ->where('organization_id', $organization->getKey())
            ->whereDate('event_date', '>=', $start)
            ->whereDate('event_date', '<=', $end)
            ->when($grade || $class, fn ($query) => $query->whereHas('learner', function ($q) use ($grade, $class): void {
                $grade && $q->where('current_grade_id', $grade->getKey());
                $class && $q->where('current_class_id', $class->getKey());
            }))
            ->selectRaw('learner_profile_id, SUM(points) as total_points')
            ->groupBy('learner_profile_id')
            ->get();

        if ($totals->isEmpty()) {
            throw new DomainException('No sports records were found for this date range and scope — log sports results first.');
        }

        $rows = $totals->map(fn ($row) => ['learner_profile_id' => $row->learner_profile_id, 'value' => (float) $row->total_points]);
        $name = trim((string) ($data['name'] ?? '')) ?: $this->defaultSportsName($start, $end, $grade, $class);

        return $this->save($organization, $actor, LeaderboardType::Sports, $name, $rows, [
            'subject_id' => null, 'grade_id' => $grade?->getKey(), 'class_id' => $class?->getKey(),
            'reporting_period_id' => null, 'period_start_date' => $start, 'period_end_date' => $end,
        ]);
    }

    public function publish(Leaderboard $leaderboard, User $actor): Leaderboard
    {
        $leaderboard->update(['visibility' => LeaderboardVisibility::Published, 'published_at' => now(), 'published_by' => $actor->getKey()]);
        $this->audit->record('leaderboards.published', $leaderboard, after: ['organization_id' => $leaderboard->organization_id, 'type' => $leaderboard->type->value]);

        return $leaderboard->refresh();
    }

    public function unpublish(Leaderboard $leaderboard, User $actor): Leaderboard
    {
        $leaderboard->update(['visibility' => LeaderboardVisibility::Private, 'published_at' => null, 'published_by' => null]);
        $this->audit->record('leaderboards.unpublished', $leaderboard, after: ['organization_id' => $leaderboard->organization_id, 'type' => $leaderboard->type->value]);

        return $leaderboard->refresh();
    }

    /** @param  Collection<int, array{learner_profile_id: string, value: float}>  $rows */
    private function save(Organization $organization, User $actor, LeaderboardType $type, string $name, Collection $rows, array $scope): Leaderboard
    {
        return DB::transaction(function () use ($organization, $actor, $type, $name, $rows, $scope): Leaderboard {
            $leaderboard = Leaderboard::query()->create([
                'organization_id' => $organization->getKey(), 'type' => $type, 'name' => $name,
                ...$scope, 'visibility' => LeaderboardVisibility::Private,
                'generated_at' => now(), 'generated_by' => $actor->getKey(),
            ]);

            $ranked = $this->rank($rows);
            foreach ($ranked as $entry) {
                LeaderboardEntry::query()->create([
                    'organization_id' => $organization->getKey(), 'leaderboard_id' => $leaderboard->getKey(),
                    'learner_profile_id' => $entry['learner_profile_id'], 'rank' => $entry['rank'], 'value' => $entry['value'],
                ]);
            }

            $this->audit->record('leaderboards.generated', $leaderboard, after: ['organization_id' => $organization->getKey(), 'type' => $type->value, 'entry_count' => $ranked->count()]);

            return $leaderboard->load('entries');
        }, 3);
    }

    /**
     * Standard competition ranking (1, 2, 2, 4 — never 1, 2, 2, 3): a
     * tie shares the same rank, and the rank after a tie skips ahead by
     * the number of tied entries, exactly like a real school assembly
     * would read out positions.
     *
     * @param  Collection<int, array{learner_profile_id: string, value: float}>  $rows
     * @return Collection<int, array{learner_profile_id: string, value: float, rank: int}>
     */
    private function rank(Collection $rows): Collection
    {
        $sorted = $rows->sortByDesc('value')->values();

        return $sorted->map(fn ($row) => [...$row, 'rank' => 1 + $sorted->where('value', '>', $row['value'])->count()]);
    }

    private function defaultAcademicName(ReportingPeriod $period, ?Subject $subject, ?Grade $grade, ?ClassGroup $class): string
    {
        $label = $subject?->getAttribute('name') ?? 'Overall Average';
        $scope = $class?->getAttribute('name') ?? $grade?->getAttribute('name') ?? 'Whole School';

        return "{$label} — {$scope} — {$period->getAttribute('name')}";
    }

    private function defaultSportsName(string $start, string $end, ?Grade $grade, ?ClassGroup $class): string
    {
        $scope = $class?->getAttribute('name') ?? $grade?->getAttribute('name') ?? 'Whole School';

        return "Sports — {$scope} — {$start} to {$end}";
    }

    private function owned(string $model, mixed $id, Organization $organization): mixed
    {
        if (! is_string($id) || $id === '') {
            return null;
        }
        $record = $model::query()->withoutGlobalScopes()->where('organization_id', $organization->getKey())->find($id);
        if ($record === null) {
            throw new DomainException('Every scope filter must belong to the active organization.');
        }

        return $record;
    }
}
