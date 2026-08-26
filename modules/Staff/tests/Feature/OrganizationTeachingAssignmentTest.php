<?php

declare(strict_types=1);

namespace Modules\Staff\Tests\Feature;

use Core\Identity\Infrastructure\Models\Membership;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Infrastructure\Models\OrganizationModule;
use Modules\Staff\Application\TeachingAssignmentService;
use Modules\Staff\Database\Seeders\StaffPermissionSeeder;
use Modules\Staff\Infrastructure\Models\StaffProfile;
use Tests\TestCase;

final class OrganizationTeachingAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_organization_wide_assignments_with_filters(): void
    {
        $c = $this->context('list');
        $staffB = $this->staffProfile($c['organization'], 'EMP-list-2');
        app(TeachingAssignmentService::class)->assign($c['organization'], $c['staff'], ['class_id' => $c['classA']->id, 'academic_year_id' => $c['year']->id], $c['actor']);
        $second = app(TeachingAssignmentService::class)->assign($c['organization'], $staffB, ['class_id' => $c['classB']->id, 'academic_year_id' => $c['year']->id], $c['actor']);
        app(TeachingAssignmentService::class)->end($second, $c['actor']);

        $this->withHeaders($c['headers'])->getJson('/api/v1/teaching-assignments')
            ->assertOk()->assertJsonCount(2, 'data');

        $this->withHeaders($c['headers'])->getJson('/api/v1/teaching-assignments?class_id='.$c['classA']->id)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.class_id', $c['classA']->id);

        $this->withHeaders($c['headers'])->getJson('/api/v1/teaching-assignments?active_only=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_active', true);
    }

    public function test_bulk_store_creates_valid_rows_and_reports_failures_independently(): void
    {
        $c = $this->context('bulk');
        $staffB = $this->staffProfile($c['organization'], 'EMP-bulk-2');
        $foreign = Organization::query()->create(['name' => 'foreign-bulk', 'code' => 'foreign-bulk', 'type' => 'school']);
        $foreignYear = AcademicYear::query()->create(['organization_id' => $foreign->id, 'name' => 'F2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $foreignGrade = Grade::query()->create(['organization_id' => $foreign->id, 'name' => 'FG', 'order' => 1, 'academic_year_id' => $foreignYear->id]);
        $foreignClass = ClassGroup::query()->create(['organization_id' => $foreign->id, 'name' => 'F1', 'academic_year_id' => $foreignYear->id, 'grade_id' => $foreignGrade->id]);

        $response = $this->withHeaders($c['headers'])->postJson('/api/v1/teaching-assignments/bulk', [
            'assignments' => [
                ['staff_profile_id' => $c['staff']->id, 'class_id' => $c['classA']->id, 'academic_year_id' => $c['year']->id],
                ['staff_profile_id' => $staffB->id, 'class_id' => $foreignClass->id, 'academic_year_id' => $c['year']->id],
                ['staff_profile_id' => $staffB->id, 'class_id' => $c['classB']->id, 'academic_year_id' => $c['year']->id, 'subject_id' => $c['subject']->id],
            ],
        ])->assertCreated();

        $response->assertJsonCount(2, 'data.created')->assertJsonCount(1, 'data.failed');
        $this->assertSame(1, $response->json('data.failed.0.index'));
        $this->assertDatabaseCount('staff_teaching_assignments', 2);
    }

    public function test_cross_organization_isolation_and_permission_gating(): void
    {
        $c = $this->context('isolation');
        $foreign = $this->context('isolation-foreign');
        app(TeachingAssignmentService::class)->assign($foreign['organization'], $foreign['staff'], ['class_id' => $foreign['classA']->id, 'academic_year_id' => $foreign['year']->id], $foreign['actor']);
        app(TeachingAssignmentService::class)->assign($c['organization'], $c['staff'], ['class_id' => $c['classA']->id, 'academic_year_id' => $c['year']->id], $c['actor']);

        // context() re-acts as its own actor each call, so re-assert identity
        // before exercising isolation for the first (non-foreign) org.
        $this->actingAs($c['actor'], 'sanctum');
        $this->withHeaders($c['headers'])->getJson('/api/v1/teaching-assignments')
            ->assertOk()->assertJsonCount(1, 'data');

        $teacherRole = Role::query()->firstOrCreate(['name' => 'Teacher'], ['is_system' => false]);
        $teacherUser = User::factory()->create();
        Membership::query()->create(['organization_id' => $c['organization']->id, 'user_id' => $teacherUser->id, 'role_id' => $teacherRole->id, 'status' => 'active', 'is_default' => true]);

        $this->actingAs($teacherUser, 'sanctum')->withHeader('X-Organization-Id', $c['organization']->id)
            ->postJson('/api/v1/teaching-assignments/bulk', [
                'assignments' => [['staff_profile_id' => $c['staff']->id, 'class_id' => $c['classA']->id, 'academic_year_id' => $c['year']->id]],
            ])->assertForbidden();
    }

    private function staffProfile(Organization $organization, string $employeeNumber): StaffProfile
    {
        $user = User::factory()->create();
        $membership = Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'status' => 'active']);

        return StaffProfile::query()->create([
            'organization_id' => $organization->id,
            'organization_membership_id' => $membership->getKey(),
            'user_id' => $membership->getAttribute('user_id'),
            'employee_number' => $employeeNumber,
            'first_name' => 'Test', 'last_name' => 'Teacher',
            'staff_type' => 'teacher', 'employment_status' => 'active',
        ]);
    }

    /** @return array{organization: Organization, actor: User, headers: array, staff: StaffProfile, year: AcademicYear, classA: ClassGroup, classB: ClassGroup, subject: Subject} */
    private function context(string $code): array
    {
        $this->seed(StaffPermissionSeeder::class);
        $organization = Organization::query()->create(['name' => $code, 'code' => $code, 'type' => 'school']);
        OrganizationModule::query()->create(['organization_id' => $organization->id, 'module_name' => 'staff', 'enabled' => true]);
        $adminRole = Role::query()->firstOrCreate(['name' => 'Organization Administrator'], ['description' => 'Organization administration', 'is_system' => false]);
        $actor = User::factory()->create();
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $actor->id, 'role_id' => $adminRole->id, 'status' => 'active', 'is_default' => true]);
        $this->actingAs($actor, 'sanctum');
        $staff = $this->staffProfile($organization, 'EMP-'.$code);
        $year = AcademicYear::query()->create(['organization_id' => $organization->id, 'name' => '2026 '.$code, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'current']);
        $grade = Grade::query()->create(['organization_id' => $organization->id, 'name' => 'Grade '.$code, 'order' => 1, 'academic_year_id' => $year->id]);
        $classA = ClassGroup::query()->create(['organization_id' => $organization->id, 'name' => 'A '.$code, 'academic_year_id' => $year->id, 'grade_id' => $grade->id]);
        $classB = ClassGroup::query()->create(['organization_id' => $organization->id, 'name' => 'B '.$code, 'academic_year_id' => $year->id, 'grade_id' => $grade->id]);
        $subject = Subject::query()->create(['organization_id' => $organization->id, 'name' => 'Mathematics '.$code, 'code' => 'M-'.$code]);

        return compact('organization', 'actor', 'staff', 'year', 'classA', 'classB', 'subject') + ['headers' => ['X-Organization-Id' => $organization->id]];
    }
}
