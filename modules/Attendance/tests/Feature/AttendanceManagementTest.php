<?php

declare(strict_types=1);

namespace Modules\Attendance\Tests\Feature;

use Core\Identity\Infrastructure\Models\Membership;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Attendance\Application\AttendanceRecordingService;
use Modules\Attendance\Application\AttendanceSessionService;
use Modules\Attendance\Database\Seeders\AttendancePermissionSeeder;
use Modules\Attendance\Domain\Enums\AttendanceSessionStatus;
use Modules\Attendance\Infrastructure\Models\AttendanceSession;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Infrastructure\Models\OrganizationModule;
use Tests\TestCase;

final class AttendanceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_populate_only_eligible_learners_record_atomically_finalize_and_reopen(): void
    {
        [$organization, $user, $membership, $year, $class] = $this->context('service');
        $eligible = LearnerProfile::factory()->create(['organization_id' => $organization->id, 'current_academic_year_id' => $year->id, 'current_class_id' => $class->id, 'learner_status' => 'active']);
        LearnerProfile::factory()->create(['organization_id' => $organization->id, 'current_academic_year_id' => $year->id, 'current_class_id' => $class->id, 'learner_status' => 'withdrawn']);
        $this->actingAs($user)->withSession(['organization_id' => $membership->organization_id]);
        $sessions = app(AttendanceSessionService::class);
        $session = $sessions->create($organization, $user, ['academic_year_id' => $year->id, 'class_id' => $class->id, 'session_date' => '2026-07-15', 'start_time' => '08:00', 'session_type' => 'class']);
        $this->assertCount(1, $session->entries);
        $this->assertSame($eligible->id, $session->entries->first()->learner_profile_id);

        $entry = $session->entries->first();
        app(AttendanceRecordingService::class)->record($session, $user, [['entry_uuid' => $entry->uuid, 'status' => 'late', 'arrival_time' => '08:12', 'reason' => '=unsafe']]);
        $this->assertDatabaseHas('attendance_entries', ['id' => $entry->id, 'status' => 'late', 'minutes_late' => 12]);
        $sessions->finalize($session->refresh(), $user);
        $this->assertSame(AttendanceSessionStatus::Finalized, $session->refresh()->status);
        $this->expectException(DomainException::class);
        app(AttendanceRecordingService::class)->record($session->refresh(), $user, [['entry_uuid' => $entry->uuid, 'status' => 'present']]);
    }

    public function test_web_api_security_lifecycle_history_summary_and_csv(): void
    {
        [$organization, $user, $membership, $year, $class] = $this->context('http');
        $learner = LearnerProfile::factory()->create(['organization_id' => $organization->id, 'current_academic_year_id' => $year->id, 'current_class_id' => $class->id, 'learner_status' => 'active']);
        $web = $this->actingAs($user)->withSession(['organization_id' => $membership->organization_id]);
        $web->get('/attendance')->assertOk()->assertSee('Attendance management');
        $web->post('/attendance', ['organization_id' => 'forged', 'academic_year_id' => $year->id, 'class_id' => $class->id, 'session_date' => '2026-07-15', 'session_type' => 'class'])->assertSessionHasErrors('organization_id');
        $web->post('/attendance', ['academic_year_id' => $year->id, 'class_id' => $class->id, 'session_date' => '2026-07-15', 'start_time' => '08:00', 'session_type' => 'class'])->assertRedirect();
        $session = AttendanceSession::query()->firstOrFail()->load('entries');
        $entry = $session->entries->first();
        $web->post(route('attendance.register.store', $session->uuid), ['entries' => [['entry_uuid' => $entry->uuid, 'status' => 'present']]])->assertRedirect();
        $web->post(route('attendance.finalize', $session->uuid))->assertRedirect();
        $web->get(route('learners.attendance', $learner->uuid))->assertOk()->assertSee('recorded finalized sessions');
        $web->get(route('attendance.export', $session->uuid))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $headers = ['X-Organization-Id' => $organization->id];
        $this->actingAs($user, 'sanctum')->withHeaders($headers)->getJson('/api/v1/attendance/reports/summary')->assertOk()->assertJsonPath('finalized_session_count', 1)->assertJsonPath('status_totals.present', 1);
        $this->withHeaders($headers)->postJson('/api/v1/attendance/sessions/'.$session->uuid.'/register', ['entries' => [['entry_uuid' => $entry->uuid, 'status' => 'absent']]])->assertForbidden();
        $this->withHeaders($headers)->postJson('/api/v1/attendance/sessions/'.$session->uuid.'/reopen', [])->assertUnprocessable();
        $this->withHeaders($headers)->postJson('/api/v1/attendance/sessions/'.$session->uuid.'/reopen', ['reason' => 'Correction required'])->assertOk()->assertJsonPath('data.status', 'open');
    }

    public function test_foreign_session_is_not_resolved_and_mutations_have_no_get_routes(): void
    {
        [$organization, $user, $membership] = $this->context('secure-a');
        [$foreign, $foreignUser, , $year, $class] = $this->context('secure-b');
        $foreignSession = app(AttendanceSessionService::class)->create($foreign, $foreignUser, ['academic_year_id' => $year->id, 'class_id' => $class->id, 'session_date' => '2026-07-15', 'session_type' => 'class']);
        $web = $this->actingAs($user)->withSession(['organization_id' => $membership->organization_id]);
        $web->get('/attendance/'.$foreignSession->uuid)->assertNotFound();
        $web->get('/attendance/'.$foreignSession->uuid.'/finalize')->assertStatus(405);
        auth()->logout();
        $this->get('/attendance')->assertRedirect('/login');
    }

    private function context(string $code): array
    {
        $this->seed(AttendancePermissionSeeder::class);
        $organization = Organization::query()->create(['name' => $code, 'code' => $code, 'type' => 'school']);
        foreach (['attendance', 'academics', 'learners', 'staff'] as $module) {
            OrganizationModule::query()->create(['organization_id' => $organization->id, 'module_name' => $module, 'enabled' => true]);
        }
        $user = User::factory()->create();
        $role = Role::query()->where('name', 'Organization Administrator')->firstOrFail();
        $membership = Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
        $year = AcademicYear::query()->create(['organization_id' => $organization->id, 'name' => '2026 '.$code, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $grade = Grade::query()->create(['organization_id' => $organization->id, 'academic_year_id' => $year->id, 'name' => 'Grade '.$code, 'order' => 1]);
        $class = ClassGroup::query()->create(['organization_id' => $organization->id, 'academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => 'Class '.$code]);

        return [$organization, $user, $membership, $year, $class];
    }
}
