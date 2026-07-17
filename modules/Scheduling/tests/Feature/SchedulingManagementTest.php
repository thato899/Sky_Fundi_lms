<?php

declare(strict_types=1);

namespace Modules\Scheduling\Tests\Feature;

use Carbon\CarbonImmutable;
use Core\Identity\Infrastructure\Models\Membership;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Academics\Infrastructure\Models\AcademicTerm;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\CalendarEntry;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Infrastructure\Models\OrganizationModule;
use Modules\Scheduling\Application\LessonService;
use Modules\Scheduling\Application\ScheduleConflictService;
use Modules\Scheduling\Application\TimetableMaterializationService;
use Modules\Scheduling\Application\TimetableService;
use Modules\Scheduling\Database\Seeders\SchedulingPermissionSeeder;
use Modules\Scheduling\Domain\Enums\LessonStatus;
use Modules\Scheduling\Infrastructure\Models\Room;
use Modules\Scheduling\Infrastructure\Models\ScheduledLesson;
use Modules\Staff\Infrastructure\Models\StaffProfile;
use Tests\TestCase;

final class SchedulingManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_contains_owned_scheduling_and_integration_columns(): void
    {
        foreach (['scheduling_rooms', 'timetable_templates', 'timetable_template_entries', 'scheduled_lessons', 'scheduled_lesson_staff', 'schedule_change_logs'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $this->assertTrue(Schema::hasColumns('scheduled_lessons', ['uuid', 'organization_id', 'timetable_template_entry_id', 'starts_at', 'ends_at', 'rescheduled_from_lesson_id']));
        $this->assertTrue(Schema::hasColumn('attendance_sessions', 'scheduled_lesson_id'));
        $this->assertTrue(Schema::hasColumn('assessments', 'scheduled_lesson_id'));
    }

    public function test_conflicts_detect_class_staff_room_and_closure_but_not_adjacency_or_cancelled(): void
    {
        $c = $this->context('conflict');
        $service = app(LessonService::class);
        $room = $this->room($c);
        $lesson = $service->create($c['organization'], $c['user'], $this->lessonData($c, ['room_id' => $room->id, 'staff' => [['staff_profile_id' => $c['staff']->id, 'assignment_type' => 'teacher', 'is_primary' => true]]]));
        $proposal = $this->normalized($c, '2026-02-02', '09:00', '10:00', ['room_id' => $room->id, 'staff_ids' => [$c['staff']->id]]);
        $types = collect(app(ScheduleConflictService::class)->lesson($c['organization']->id, $proposal))->pluck('type');
        $this->assertContains('class', $types);
        $this->assertContains('room', $types);
        $this->assertContains('staff', $types);
        $this->assertSame([], app(ScheduleConflictService::class)->lesson($c['organization']->id, $this->normalized($c, '2026-02-02', '10:00', '11:00', ['room_id' => $room->id])));
        $lesson->update(['status' => 'cancelled']);
        $this->assertSame([], app(ScheduleConflictService::class)->lesson($c['organization']->id, $proposal));
        CalendarEntry::query()->create(['organization_id' => $c['organization']->id, 'academic_year_id' => $c['year']->id, 'type' => 'public_holiday', 'name' => 'Closure', 'start_date' => '2026-02-03', 'end_date' => '2026-02-03', 'affects_teaching' => true, 'closure_scope' => 'organization']);
        $this->assertSame('closure', app(ScheduleConflictService::class)->lesson($c['organization']->id, $this->normalized($c, '2026-02-03', '09:00', '10:00'))[0]['type']);
    }

    public function test_lesson_conflict_query_count_is_bounded_as_overlapping_lessons_grow(): void
    {
        $c = $this->context('conflict-query-count');
        $attributes = $this->lessonData($c) + [
            'organization_id' => $c['organization']->id,
            'starts_at' => CarbonImmutable::parse('2026-02-02 09:00', $c['organization']->timezone)->utc(),
            'ends_at' => CarbonImmutable::parse('2026-02-02 10:00', $c['organization']->timezone)->utc(),
            'created_by' => $c['user']->id,
        ];

        foreach (range(1, 5) as $number) {
            $lesson = ScheduledLesson::query()->create($attributes + ['title' => 'Overlap '.$number]);
            $lesson->staff()->attach($c['staff']->id, [
                'id' => (string) Str::uuid(),
                'organization_id' => $c['organization']->id,
                'assignment_type' => 'teacher',
                'is_primary' => true,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $conflicts = app(ScheduleConflictService::class)->lesson(
                $c['organization']->id,
                $this->normalized($c, '2026-02-02', '09:15', '09:45', ['staff_ids' => [$c['staff']->id]]),
            );
            $selects = collect(DB::getQueryLog())->filter(
                fn (array $query): bool => str_starts_with(strtolower(ltrim($query['query'])), 'select'),
            );
        } finally {
            DB::disableQueryLog();
        }

        $this->assertCount(10, $conflicts);
        $this->assertCount(2, $selects);
    }

    public function test_template_activation_materialization_closure_skip_and_idempotency(): void
    {
        $c = $this->context('template');
        $templates = app(TimetableService::class);
        $template = $templates->create($c['organization'], $c['user'], ['academic_year_id' => $c['year']->id, 'academic_term_id' => $c['term']->id, 'name' => 'Term timetable', 'effective_start_date' => '2026-01-01', 'effective_end_date' => '2026-03-31']);
        $templates->addEntry($template, ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '10:00', 'grade_id' => $c['grade']->id, 'class_id' => $c['class']->id, 'subject_id' => $c['subject']->id, 'delivery_mode' => 'in_person']);
        $templates->activate($template->refresh(), $c['user']);
        CalendarEntry::query()->create(['organization_id' => $c['organization']->id, 'academic_year_id' => $c['year']->id, 'type' => 'public_holiday', 'name' => 'Closed Monday', 'start_date' => '2026-02-09', 'end_date' => '2026-02-09', 'affects_teaching' => true, 'closure_scope' => 'organization']);
        $result = app(TimetableMaterializationService::class)->materialize($template->refresh(), $c['organization'], $c['user'], '2026-02-02', '2026-02-16');
        $this->assertSame(2, $result['created']);
        $this->assertSame(1, $result['conflicted']);
        $again = app(TimetableMaterializationService::class)->materialize($template->refresh(), $c['organization'], $c['user'], '2026-02-02', '2026-02-16');
        $this->assertSame(0, $again['created']);
        $this->assertSame(2, $again['skipped']);
        $this->assertSame(2, ScheduledLesson::query()->count());
    }

    public function test_lesson_staff_lifecycle_attendance_and_history_are_preserved(): void
    {
        $c = $this->context('lifecycle');
        $service = app(LessonService::class);
        $lesson = $service->create($c['organization'], $c['user'], $this->lessonData($c, ['staff' => [['staff_profile_id' => $c['staff']->id, 'assignment_type' => 'teacher', 'is_primary' => true]]]));
        $this->assertCount(1, $lesson->staff);
        $attendance = $service->createAttendance($lesson, $c['organization'], $c['user']);
        $this->assertSame($attendance->id, $service->createAttendance($lesson, $c['organization'], $c['user'])->id);
        $replacement = $service->reschedule($lesson->refresh(), $c['organization'], $c['user'], ['lesson_date' => '2026-02-03', 'start_time' => '09:00', 'end_time' => '10:00', 'reason' => 'Assembly']);
        $this->assertSame(LessonStatus::Rescheduled, $lesson->refresh()->status);
        $this->assertSame($lesson->id, $replacement->rescheduled_from_lesson_id);
        $this->assertDatabaseHas('attendance_sessions', ['id' => $attendance->id, 'scheduled_lesson_id' => $lesson->id]);
        $service->cancel($replacement, $c['user'], 'Teacher unavailable');
        $this->assertSame(LessonStatus::Cancelled, $replacement->refresh()->status);
        $this->assertGreaterThanOrEqual(2, $lesson->changes()->count());
    }

    public function test_api_web_export_and_foreign_uuid_boundaries(): void
    {
        $a = $this->context('http-a');
        $b = $this->context('http-b');
        $foreign = $this->room($b);
        $headers = ['X-Organization-Id' => $a['organization']->id];
        $this->actingAs($a['user'])->withSession(['organization_id' => $a['organization']->id])->get('/scheduling')->assertOk()->assertSee('Lessons today');
        $this->get('/scheduling/timetable')->assertOk()->assertSee($a['organization']->timezone);
        $this->actingAs($a['user'], 'sanctum')->withHeaders($headers)->getJson('/api/v1/scheduling/rooms/'.$foreign->uuid)->assertNotFound();
        $this->withHeaders($headers)->postJson('/api/v1/scheduling/rooms', ['organization_id' => $b['organization']->id, 'name' => 'Lab', 'location_type' => 'laboratory'])->assertCreated()->assertJsonMissing(['organization_id' => $b['organization']->id]);
        $this->withHeaders($headers)->getJson('/api/v1/scheduling/export?date_from=2026-01-01&date_to=2026-03-31')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        auth('web')->logout();
        $this->app->make('auth')->forgetGuards();
        $this->get('/scheduling')->assertRedirect('/login');
        $this->get('/api/v1/scheduling/lessons')->assertUnauthorized();
    }

    private function context(string $code): array
    {
        $this->seed(SchedulingPermissionSeeder::class);
        $o = Organization::query()->create(['name' => $code, 'code' => $code, 'type' => 'school', 'timezone' => 'Africa/Johannesburg']);
        foreach (['academics', 'staff', 'attendance', 'assessments', 'scheduling'] as $m) {
            OrganizationModule::query()->create(['organization_id' => $o->id, 'module_name' => $m, 'enabled' => true]);
        }
        $u = User::factory()->create();
        $role = Role::query()->where('name', 'Organization Administrator')->firstOrFail();
        $membership = Membership::query()->create(['organization_id' => $o->id, 'user_id' => $u->id, 'role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
        $year = AcademicYear::query()->create(['organization_id' => $o->id, 'name' => '2026 '.$code, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $term = AcademicTerm::query()->create(['organization_id' => $o->id, 'academic_year_id' => $year->id, 'term_number' => 1, 'name' => 'Term 1', 'start_date' => '2026-01-01', 'end_date' => '2026-03-31']);
        $grade = Grade::query()->create(['organization_id' => $o->id, 'academic_year_id' => $year->id, 'name' => 'Grade '.$code, 'order' => 1]);
        $class = ClassGroup::query()->create(['organization_id' => $o->id, 'academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => 'Class '.$code]);
        $subject = Subject::query()->create(['organization_id' => $o->id, 'name' => 'Math '.$code, 'code' => 'M'.$code]);
        $staffUser = User::factory()->create();
        $staffMembership = Membership::query()->create(['organization_id' => $o->id, 'user_id' => $staffUser->id, 'role_id' => $role->id, 'status' => 'active']);
        $staff = StaffProfile::query()->create(['organization_id' => $o->id, 'organization_membership_id' => $staffMembership->id, 'user_id' => $staffUser->id, 'employee_number' => 'E'.$code, 'staff_type' => 'teacher', 'employment_status' => 'active', 'first_name' => 'Teacher', 'last_name' => $code]);
        $this->actingAs($u)->withSession(['organization_id' => $o->id]);

        return ['organization' => $o, 'user' => $u, 'year' => $year, 'term' => $term, 'grade' => $grade, 'class' => $class, 'subject' => $subject, 'staff' => $staff];
    }

    private function room(array $c): Room
    {
        return Room::query()->create(['organization_id' => $c['organization']->id, 'name' => 'Room '.$c['organization']->code, 'location_type' => 'classroom', 'is_active' => true, 'created_by' => $c['user']->id]);
    }

    private function lessonData(array $c, array $extra = []): array
    {
        return [...['academic_year_id' => $c['year']->id, 'academic_term_id' => $c['term']->id, 'grade_id' => $c['grade']->id, 'class_id' => $c['class']->id, 'subject_id' => $c['subject']->id, 'lesson_date' => '2026-02-02', 'start_time' => '09:00', 'end_time' => '10:00', 'delivery_mode' => 'in_person'], ...$extra];
    }

    private function normalized(array $c, string $date, string $start, string $end, array $extra = []): array
    {
        return [...$this->lessonData($c, ['lesson_date' => $date]), 'starts_at' => CarbonImmutable::parse($date.' '.$start, $c['organization']->timezone)->utc(), 'ends_at' => CarbonImmutable::parse($date.' '.$end, $c['organization']->timezone)->utc(), ...$extra];
    }
}
