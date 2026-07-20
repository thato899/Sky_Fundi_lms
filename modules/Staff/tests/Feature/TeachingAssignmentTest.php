<?php

declare(strict_types=1);

namespace Modules\Staff\Tests\Feature;

use Core\Identity\Infrastructure\Models\Membership;
use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Academics\Infrastructure\Models\AcademicTerm;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Assessments\Application\AssessmentService;
use Modules\Assessments\Infrastructure\Models\AssessmentCategory;
use Modules\Attendance\Application\AttendanceSessionService;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Infrastructure\Models\OrganizationModule;
use Modules\Organizations\Infrastructure\Models\OrganizationSetting;
use Modules\Staff\Application\TeachingAssignmentService;
use Modules\Staff\Database\Seeders\StaffPermissionSeeder;
use Modules\Staff\Infrastructure\Models\StaffProfile;
use Modules\Staff\Infrastructure\Models\TeachingAssignment;
use Tests\TestCase;

final class TeachingAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_creates_assignment_schema(): void
    {
        $this->assertTrue(Schema::hasTable('staff_teaching_assignments'));
        $this->assertTrue(Schema::hasColumns('staff_teaching_assignments', [
            'organization_id', 'staff_profile_id', 'class_id', 'subject_id',
            'academic_year_id', 'started_on', 'ended_on', 'actor_id',
        ]));
    }

    public function test_assign_validates_ownership_prevents_duplicates_and_end_closes(): void
    {
        $c = $this->context('assign');
        $service = app(TeachingAssignmentService::class);

        $foreign = Organization::query()->create(['name' => 'other', 'code' => 'other', 'type' => 'school']);
        $foreignYear = AcademicYear::query()->create(['organization_id' => $foreign->id, 'name' => 'F2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $foreignGrade = Grade::query()->create(['organization_id' => $foreign->id, 'name' => 'FG', 'order' => 1, 'academic_year_id' => $foreignYear->id]);
        $foreignClass = ClassGroup::query()->create(['organization_id' => $foreign->id, 'name' => 'F1', 'academic_year_id' => $foreignYear->id, 'grade_id' => $foreignGrade->id]);
        try {
            $service->assign($c['organization'], $c['staff'], ['class_id' => $foreignClass->id, 'academic_year_id' => $c['year']->id], $c['actor']);
            $this->fail('Foreign class accepted.');
        } catch (DomainException) {
            $this->assertDatabaseCount('staff_teaching_assignments', 0);
        }

        $assignment = $service->assign($c['organization'], $c['staff'], ['class_id' => $c['class']->id, 'subject_id' => $c['subject']->id, 'academic_year_id' => $c['year']->id], $c['actor']);
        $this->assertNull($assignment->ended_on);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.teaching_assignment_created']);

        try {
            $service->assign($c['organization'], $c['staff'], ['class_id' => $c['class']->id, 'subject_id' => $c['subject']->id, 'academic_year_id' => $c['year']->id], $c['actor']);
            $this->fail('Duplicate open assignment accepted.');
        } catch (DomainException) {
            $this->assertDatabaseCount('staff_teaching_assignments', 1);
        }

        $homeroom = $service->assign($c['organization'], $c['staff'], ['class_id' => $c['class']->id, 'academic_year_id' => $c['year']->id], $c['actor']);
        $this->assertDatabaseCount('staff_teaching_assignments', 2);

        $ended = $service->end($homeroom, $c['actor']);
        $this->assertNotNull($ended->ended_on);
        try {
            $service->end($ended, $c['actor']);
            $this->fail('Ending an ended assignment accepted.');
        } catch (DomainException) {
        }
    }

    public function test_is_assigned_resolves_subject_semantics_and_date_windows(): void
    {
        $c = $this->context('window');
        $other = Subject::query()->create(['organization_id' => $c['organization']->id, 'name' => 'Science', 'code' => 'SCI-window']);
        $base = ['organization_id' => $c['organization']->id, 'staff_profile_id' => $c['staff']->id, 'class_id' => $c['class']->id, 'academic_year_id' => $c['year']->id];
        TeachingAssignment::query()->create([...$base, 'subject_id' => $c['subject']->id, 'started_on' => '2026-01-10']);
        $service = app(TeachingAssignmentService::class);

        $this->assertTrue($service->isAssigned($c['organization']->id, $c['staff']->id, $c['class']->id, $c['subject']->id));
        $this->assertFalse($service->isAssigned($c['organization']->id, $c['staff']->id, $c['class']->id, $other->id));
        $this->assertTrue($service->isAssigned($c['organization']->id, $c['staff']->id, $c['class']->id));

        TeachingAssignment::query()->create([...$base, 'subject_id' => null, 'started_on' => '2026-01-10', 'ended_on' => '2026-02-28']);
        $this->assertTrue($service->isAssigned($c['organization']->id, $c['staff']->id, $c['class']->id, $other->id, '2026-02-01'));
        $this->assertFalse($service->isAssigned($c['organization']->id, $c['staff']->id, $c['class']->id, $other->id, '2026-03-01'));
    }

    public function test_enforcement_gates_assessment_and_attendance_staffing(): void
    {
        $c = $this->context('enforce');
        $this->enforce($c['organization']);
        $category = AssessmentCategory::query()->create(['organization_id' => $c['organization']->id, 'name' => 'Tests', 'is_active' => true, 'created_by' => $c['actor']->id, 'updated_by' => $c['actor']->id]);
        $assessmentData = ['academic_year_id' => $c['year']->id, 'academic_term_id' => $c['term']->id, 'grade_id' => $c['grade']->id, 'class_id' => $c['class']->id, 'subject_id' => $c['subject']->id, 'assessment_category_id' => $category->id, 'staff_profile_id' => $c['staff']->id, 'title' => 'Gated', 'assessment_date' => '2026-03-01', 'maximum_mark' => 50];

        try {
            app(AssessmentService::class)->create($c['organization'], $c['actor'], $assessmentData);
            $this->fail('Unassigned staff accepted on assessment.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('not assigned', $exception->getMessage());
        }

        app(TeachingAssignmentService::class)->assign($c['organization'], $c['staff'], ['class_id' => $c['class']->id, 'subject_id' => $c['subject']->id, 'academic_year_id' => $c['year']->id], $c['actor']);
        $assessment = app(AssessmentService::class)->create($c['organization'], $c['actor'], $assessmentData);
        $this->assertSame($c['staff']->id, $assessment->staff_profile_id);

        $session = app(AttendanceSessionService::class)->create($c['organization'], $c['actor'], ['academic_year_id' => $c['year']->id, 'class_id' => $c['class']->id, 'session_date' => '2026-03-02', 'session_type' => 'class', 'staff_profile_id' => $c['staff']->id]);
        $this->assertSame($c['staff']->id, $session->staff_profile_id);

        $unassigned = $this->staffProfile($c['organization'], 'UNASSIGNED-1');
        try {
            app(AttendanceSessionService::class)->create($c['organization'], $c['actor'], ['academic_year_id' => $c['year']->id, 'class_id' => $c['class']->id, 'session_date' => '2026-03-03', 'session_type' => 'class', 'staff_profile_id' => $unassigned->id]);
            $this->fail('Unassigned staff accepted on attendance session.');
        } catch (DomainException) {
        }

        $relaxed = $this->context('relaxed');
        $relaxedCategory = AssessmentCategory::query()->create(['organization_id' => $relaxed['organization']->id, 'name' => 'Tests', 'is_active' => true, 'created_by' => $relaxed['actor']->id, 'updated_by' => $relaxed['actor']->id]);
        $open = app(AssessmentService::class)->create($relaxed['organization'], $relaxed['actor'], ['academic_year_id' => $relaxed['year']->id, 'academic_term_id' => $relaxed['term']->id, 'grade_id' => $relaxed['grade']->id, 'class_id' => $relaxed['class']->id, 'subject_id' => $relaxed['subject']->id, 'assessment_category_id' => $relaxedCategory->id, 'staff_profile_id' => $relaxed['staff']->id, 'title' => 'Ungated', 'assessment_date' => '2026-03-01', 'maximum_mark' => 50]);
        $this->assertSame($relaxed['staff']->id, $open->staff_profile_id);
    }

    public function test_actor_gate_uses_bypass_permission_and_staff_assignment(): void
    {
        $this->seed(StaffPermissionSeeder::class);
        $c = $this->context('actor');
        $this->enforce($c['organization']);
        $service = app(TeachingAssignmentService::class);

        $this->assertFalse($service->actorMayActOn($c['membership'], (string) $c['class']->id, (string) $c['subject']->id));

        $service->assign($c['organization'], $c['staff'], ['class_id' => $c['class']->id, 'subject_id' => $c['subject']->id, 'academic_year_id' => $c['year']->id], $c['actor']);
        $this->assertTrue($service->actorMayActOn($c['membership'], (string) $c['class']->id, (string) $c['subject']->id));
        $otherGrade = Grade::query()->create(['organization_id' => $c['organization']->id, 'name' => 'Other actor', 'order' => 2, 'academic_year_id' => $c['year']->id]);
        $otherClass = ClassGroup::query()->create(['organization_id' => $c['organization']->id, 'name' => 'Other', 'academic_year_id' => $c['year']->id, 'grade_id' => $otherGrade->id]);
        $this->assertFalse($service->actorMayActOn($c['membership'], (string) $otherClass->id, null));

        $adminRole = Role::query()->firstOrCreate(['name' => 'Bypass Admin'], ['description' => 'Bypass', 'is_system' => false]);
        $adminRole->permissions()->syncWithoutDetaching(Permission::query()->where('name', TeachingAssignmentService::BYPASS_PERMISSION)->pluck('id'));
        $adminUser = User::factory()->create();
        $adminMembership = Membership::query()->create(['organization_id' => $c['organization']->id, 'user_id' => $adminUser->id, 'role_id' => $adminRole->id, 'status' => 'active']);
        $this->assertTrue($service->actorMayActOn($adminMembership, (string) $otherClass->id, null));

        $noProfileUser = User::factory()->create();
        $noProfileMembership = Membership::query()->create(['organization_id' => $c['organization']->id, 'user_id' => $noProfileUser->id, 'status' => 'active']);
        $this->assertFalse($service->actorMayActOn($noProfileMembership, (string) $c['class']->id, null));

        $relaxed = $this->context('unenforced');
        $this->assertTrue($service->actorMayActOn($relaxed['membership'], (string) $relaxed['class']->id, null));
    }

    private function enforce(Organization $organization): void
    {
        OrganizationSetting::query()->create(['organization_id' => $organization->id, 'group' => 'staff', 'key' => 'enforce_teaching_assignments', 'value' => true]);
    }

    private function staffProfile(Organization $organization, string $employeeNumber, ?Membership $membership = null): StaffProfile
    {
        if ($membership === null) {
            $user = User::factory()->create();
            $membership = Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'status' => 'active']);
        }

        return StaffProfile::query()->create([
            'organization_id' => $organization->id,
            'organization_membership_id' => $membership->getKey(),
            'user_id' => $membership->getAttribute('user_id'),
            'employee_number' => $employeeNumber,
            'first_name' => 'Test', 'last_name' => 'Teacher',
            'staff_type' => 'teacher', 'employment_status' => 'active',
        ]);
    }

    /** @return array{organization: Organization, actor: User, membership: Membership, staff: StaffProfile, year: AcademicYear, term: AcademicTerm, grade: Grade, class: ClassGroup, subject: Subject} */
    private function context(string $code): array
    {
        $organization = Organization::query()->create(['name' => $code, 'code' => $code, 'type' => 'school']);
        foreach (['staff', 'academics', 'assessments', 'attendance'] as $module) {
            OrganizationModule::query()->create(['organization_id' => $organization->id, 'module_name' => $module, 'enabled' => true]);
        }
        $actor = User::factory()->create();
        $membership = Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $actor->id, 'status' => 'active']);
        $this->actingAs($actor);
        $staff = $this->staffProfile($organization, 'EMP-'.$code, $membership);
        $year = AcademicYear::query()->create(['organization_id' => $organization->id, 'name' => '2026 '.$code, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'current']);
        $term = AcademicTerm::query()->create(['organization_id' => $organization->id, 'academic_year_id' => $year->id, 'term_number' => 1, 'name' => 'Term 1', 'start_date' => '2026-01-01', 'end_date' => '2026-03-31']);
        $grade = Grade::query()->create(['organization_id' => $organization->id, 'name' => 'Grade '.$code, 'order' => 1, 'academic_year_id' => $year->id]);
        $class = ClassGroup::query()->create(['organization_id' => $organization->id, 'name' => 'Class '.$code, 'academic_year_id' => $year->id, 'grade_id' => $grade->id]);
        $subject = Subject::query()->create(['organization_id' => $organization->id, 'name' => 'Mathematics '.$code, 'code' => 'M-'.$code]);

        return compact('organization', 'actor', 'membership', 'staff', 'year', 'term', 'grade', 'class', 'subject');
    }
}
