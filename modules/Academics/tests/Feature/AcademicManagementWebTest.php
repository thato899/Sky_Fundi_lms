<?php

declare(strict_types=1);

namespace Modules\Academics\Tests\Feature;

use Core\Identity\Infrastructure\Models\Membership;
use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Infrastructure\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academics\Database\Seeders\AcademicsPermissionSeeder;
use Modules\Academics\Infrastructure\Models\AcademicTerm;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\CalendarEntry;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Curriculum;
use Modules\Academics\Infrastructure\Models\Department;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Academics\Infrastructure\Models\TimetablePeriod;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Infrastructure\Models\OrganizationModule;
use Tests\TestCase;

final class AcademicManagementWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_has_real_scoped_counts_and_dashboard_link(): void
    {
        [$org, $admin, $membership] = $this->member('landing');
        [$foreign] = $this->member('foreign');
        $this->year($org, 'Visible year');
        $this->year($foreign, 'Private year');
        Curriculum::create(['organization_id' => $org->id, 'name' => 'Visible curriculum', 'code' => 'VISIBLE']);
        $session = $this->actingAs($admin)->withSession(['organization_id' => $membership->organization_id]);

        $session->get('/academics')->assertOk()->assertSee('Academic years')->assertSee('Curricula')->assertDontSee('Private year');
        $session->get(route('dashboard'))->assertOk()->assertSee(route('academics.web.index'), false)->assertSee('Open academic management');
    }

    public function test_year_and_nested_term_lifecycle_use_trusted_organization(): void
    {
        [$org, $admin, $membership] = $this->member('years');
        [$foreign] = $this->member('years-private');
        $foreignYear = $this->year($foreign, 'Foreign');
        $foreignTerm = AcademicTerm::create(['organization_id' => $foreign->id, 'academic_year_id' => $foreignYear->id, 'term_number' => 1, 'name' => 'Private', 'start_date' => '2026-01-01', 'end_date' => '2026-03-01']);
        $session = $this->actingAs($admin)->withSession(['organization_id' => $membership->organization_id]);
        $session->post(route('academics.web.years.store'), ['name' => '2027', 'start_date' => '2027-01-01', 'end_date' => '2027-12-31', 'organization_id' => $foreign->id])->assertSessionHasErrors('organization_id');
        $session->post(route('academics.web.years.store'), ['name' => '2027', 'start_date' => '2027-01-01', 'end_date' => '2027-12-31'])->assertRedirect();
        $year = AcademicYear::where('organization_id', $org->id)->firstOrFail();
        $session->post(route('academics.web.terms.store', $year), ['term_number' => 1, 'name' => 'Term 1', 'start_date' => '2026-12-01', 'end_date' => '2027-03-31'])->assertSessionHasErrors('start_date');
        $session->post(route('academics.web.terms.store', $year), ['term_number' => 1, 'name' => 'Term 1', 'start_date' => '2027-01-10', 'end_date' => '2027-03-31'])->assertRedirect();
        $term = AcademicTerm::where('organization_id', $org->id)->firstOrFail();
        $session->post(route('academics.web.terms.current', [$year, $term]))->assertRedirect();
        $this->assertTrue($term->fresh()->is_current);
        $session->get(route('academics.web.years.show', $foreignYear))->assertNotFound();
        $session->get(route('academics.web.terms.edit', [$year, $foreignTerm]))->assertNotFound();
    }

    public function test_catalogs_create_update_relationships_and_reject_foreign_ids(): void
    {
        [$org, $admin, $membership] = $this->member('catalogs');
        [$foreign] = $this->member('catalog-private');
        $year = $this->year($org, '2026');
        $foreignYear = $this->year($foreign, 'Foreign');
        $foreignCurriculum = Curriculum::create(['organization_id' => $foreign->id, 'name' => 'Private', 'code' => 'PRIVATE']);
        $session = $this->actingAs($admin)->withSession(['organization_id' => $membership->organization_id]);
        $session->post(route('academics.web.curricula.store'), ['name' => 'CAPS', 'code' => 'CAPS'])->assertRedirect();
        $session->post(route('academics.web.departments.store'), ['name' => 'Science', 'code' => 'SCI'])->assertRedirect();
        $curriculum = Curriculum::where('organization_id', $org->id)->firstOrFail();
        $department = Department::where('organization_id', $org->id)->firstOrFail();
        $session->post(route('academics.web.grades.store'), ['name' => 'Grade 8', 'order' => 8, 'academic_year_id' => $year->id, 'curriculum_id' => $curriculum->id, 'status' => 'active'])->assertRedirect();
        $grade = Grade::where('organization_id', $org->id)->firstOrFail();
        $session->post(route('academics.web.classes.store'), ['name' => '8A', 'academic_year_id' => $year->id, 'grade_id' => $grade->id, 'status' => 'active'])->assertRedirect();
        $session->post(route('academics.web.subjects.store'), ['name' => 'Mathematics', 'code' => 'MATH', 'curriculum_id' => $curriculum->id, 'department_id' => $department->id, 'status' => 'active'])->assertRedirect();
        $session->post(route('academics.web.timetable-periods.store'), ['name' => 'Period 1', 'day_of_week' => 'monday', 'start_time' => '08:00', 'end_time' => '09:00', 'order' => 1, 'status' => 'active'])->assertRedirect();
        $this->assertDatabaseHas('academics_subjects', ['organization_id' => $org->id, 'curriculum_id' => $curriculum->id, 'department_id' => $department->id]);
        $session->post(route('academics.web.grades.store'), ['name' => 'Forged', 'order' => 9, 'academic_year_id' => $foreignYear->id, 'curriculum_id' => $foreignCurriculum->id])->assertSessionHasErrors(['academic_year_id', 'curriculum_id']);
        $session->post(route('academics.web.classes.store'), ['name' => 'Mismatch', 'academic_year_id' => $foreignYear->id, 'grade_id' => $grade->id])->assertSessionHasErrors('academic_year_id');
        foreach (['curricula' => Curriculum::class, 'departments' => Department::class, 'grades' => Grade::class, 'classes' => ClassGroup::class, 'subjects' => Subject::class, 'timetable-periods' => TimetablePeriod::class] as $area => $model) {
            $record = $model::where('organization_id', $org->id)->firstOrFail();
            $session->get(route("academics.web.{$area}.show", $record))->assertOk();
        }
    }

    public function test_calendar_is_year_scoped_validated_and_deletable(): void
    {
        [$org, $admin, $membership] = $this->member('calendar');
        [$foreign] = $this->member('calendar-private');
        $year = $this->year($org, '2026');
        $foreignYear = $this->year($foreign, 'Foreign');
        $session = $this->actingAs($admin)->withSession(['organization_id' => $membership->organization_id]);
        $session->post(route('academics.web.calendar.store', $year), ['name' => 'Outside', 'type' => 'event', 'start_date' => '2027-01-01', 'end_date' => '2027-01-02'])->assertSessionHasErrors('start_date');
        $session->post(route('academics.web.calendar.store', $year), ['name' => 'Opening', 'type' => 'event', 'start_date' => '2026-01-10', 'end_date' => '2026-01-10', 'description' => '<script>unsafe</script>'])->assertRedirect();
        $entry = CalendarEntry::where('organization_id', $org->id)->firstOrFail();
        $session->get(route('academics.web.calendar.index', $year))->assertOk()->assertDontSee('<script>unsafe</script>', false);
        $session->get(route('academics.web.calendar.index', $foreignYear))->assertNotFound();
        $session->delete(route('academics.web.calendar.destroy', [$year, $entry]))->assertRedirect();
        $this->assertDatabaseMissing('academics_calendar_entries', ['id' => $entry->id]);
        $session->get(route('academics.web.calendar.destroy', [$year, $entry]))->assertStatus(405);
    }

    public function test_auth_membership_permission_and_query_tenant_switch_are_denied(): void
    {
        [$org, $admin, $membership] = $this->member('security');
        [$foreign] = $this->member('security-private');
        $private = $this->year($foreign, 'Private year');
        $this->get('/academics')->assertRedirect(route('login'));
        $session = $this->actingAs($admin)->withSession(['organization_id' => $membership->organization_id]);
        $session->get('/academics?organization_id='.$foreign->id)->assertOk()->assertDontSee($private->name);
        $membership->update(['status' => 'suspended']);
        $session->get('/academics')->assertForbidden();
        $membership->update(['status' => 'active']);
        $org->update(['status' => 'suspended']);
        $session->get('/academics')->assertForbidden();
        [, $denied, $deniedMembership] = $this->member('denied', false);
        $this->actingAs($denied)->withSession(['organization_id' => $deniedMembership->organization_id])->get('/academics')->assertForbidden();
    }

    /** @return array{Organization, User, Membership} */
    private function member(string $suffix, bool $grant = true): array
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(AcademicsPermissionSeeder::class);
        $org = Organization::create(['name' => "School {$suffix}", 'code' => "school-{$suffix}", 'type' => 'school']);
        OrganizationModule::create(['organization_id' => $org->id, 'module_name' => 'academics', 'enabled' => true]);
        $role = Role::firstOrCreate(['name' => "Academic web {$suffix}"], ['is_system' => false]);
        if ($grant) {
            $role->permissions()->syncWithoutDetaching(Permission::where('name', 'like', 'academics.%')->orWhere('name', 'organization.dashboard.view')->pluck('id'));
        }
        $user = User::factory()->create();
        $membership = Membership::create(['organization_id' => $org->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active', 'is_default' => true]);

        return [$org, $user, $membership];
    }

    private function year(Organization $org, string $name): AcademicYear
    {
        return AcademicYear::create(['organization_id' => $org->id, 'name' => $name, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    }
}
