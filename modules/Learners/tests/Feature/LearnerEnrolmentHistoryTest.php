<?php

declare(strict_types=1);

namespace Modules\Learners\Tests\Feature;

use Core\Identity\Infrastructure\Models\Membership;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Learners\Application\LearnerService;
use Modules\Learners\Database\Seeders\LearnersPermissionSeeder;
use Modules\Learners\Infrastructure\Models\LearnerEnrolment;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Infrastructure\Models\OrganizationModule;
use Tests\TestCase;

final class LearnerEnrolmentHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_correct_enrolment_history(): void
    {
        [$organization, $admin] = $this->member('view-correct', 'Organization Administrator');
        $headers = ['X-Organization-Id' => $organization->id];
        [$year, $grade, $classA] = $this->academics($organization);
        $learner = app(LearnerService::class)->create($organization, $admin, [
            'first_name' => 'Enrol', 'last_name' => 'History',
            'current_academic_year_id' => $year->id, 'current_grade_id' => $grade->id, 'current_class_id' => $classA->id,
        ], false);

        $listed = $this->actingAs($admin, 'sanctum')->withHeaders($headers)
            ->getJson("/api/v1/learners/{$learner->uuid}/enrolment-history")
            ->assertOk();
        $listed->assertJsonCount(1, 'data')->assertJsonPath('data.0.class_id', $classA->id)->assertJsonPath('data.0.is_open', true);
        $enrolmentId = $listed->json('data.0.id');

        $this->actingAs($admin, 'sanctum')->withHeaders($headers)
            ->patchJson("/api/v1/learners/{$learner->uuid}/enrolment-history/{$enrolmentId}", ['started_on' => '2026-01-05'])
            ->assertOk()->assertJsonPath('data.started_on', '2026-01-05');

        $this->assertDatabaseHas('audit_logs', ['action' => 'learners.enrolment_corrected']);
        $this->assertDatabaseHas('learner_enrolments', ['id' => $enrolmentId, 'started_on' => '2026-01-05 00:00:00']);
    }

    public function test_correction_rejects_end_before_start_and_overlapping_ranges(): void
    {
        [$organization, $admin] = $this->member('overlap', 'Organization Administrator');
        $headers = ['X-Organization-Id' => $organization->id];
        [$year, $grade, $classA, $classB] = $this->academics($organization);
        $learner = LearnerProfile::factory()->create(['organization_id' => $organization->id]);
        $closed = LearnerEnrolment::query()->create([
            'organization_id' => $organization->id, 'learner_profile_id' => $learner->id,
            'academic_year_id' => $year->id, 'grade_id' => $grade->id, 'class_id' => $classA->id,
            'started_on' => '2026-01-01', 'ended_on' => '2026-02-14',
        ]);
        LearnerEnrolment::query()->create([
            'organization_id' => $organization->id, 'learner_profile_id' => $learner->id,
            'academic_year_id' => $year->id, 'grade_id' => $grade->id, 'class_id' => $classB->id,
            'started_on' => '2026-02-15',
        ]);

        $this->actingAs($admin, 'sanctum')->withHeaders($headers)
            ->patchJson("/api/v1/learners/{$learner->uuid}/enrolment-history/{$closed->id}", ['started_on' => '2026-02-01', 'ended_on' => '2026-01-15'])
            ->assertUnprocessable();

        $this->actingAs($admin, 'sanctum')->withHeaders($headers)
            ->patchJson("/api/v1/learners/{$learner->uuid}/enrolment-history/{$closed->id}", ['ended_on' => '2026-03-01'])
            ->assertUnprocessable();

        $this->assertDatabaseHas('learner_enrolments', ['id' => $closed->id, 'ended_on' => '2026-02-14 00:00:00']);
    }

    public function test_cross_organization_learner_and_missing_permission_are_rejected(): void
    {
        [$organization, $admin] = $this->member('isolation', 'Organization Administrator');
        [$foreignOrg] = $this->member('isolation-foreign', 'Organization Administrator');
        $foreignLearner = LearnerProfile::factory()->create(['organization_id' => $foreignOrg->id]);

        $this->actingAs($admin, 'sanctum')->withHeader('X-Organization-Id', $organization->id)
            ->getJson("/api/v1/learners/{$foreignLearner->uuid}/enrolment-history")
            ->assertNotFound();

        $teacherRole = Role::query()->firstOrCreate(['name' => 'Teacher'], ['is_system' => false]);
        $teacher = User::factory()->create();
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $teacher->id, 'role_id' => $teacherRole->id, 'status' => 'active', 'is_default' => true]);
        $learner = LearnerProfile::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($teacher, 'sanctum')->withHeader('X-Organization-Id', $organization->id)
            ->getJson("/api/v1/learners/{$learner->uuid}/enrolment-history")
            ->assertForbidden();
    }

    /** @return array{AcademicYear, Grade, ClassGroup, ClassGroup} */
    private function academics(Organization $organization): array
    {
        $year = AcademicYear::query()->create(['organization_id' => $organization->id, 'name' => '2026 '.$organization->code, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'current']);
        $grade = Grade::query()->create(['organization_id' => $organization->id, 'name' => 'Grade '.$organization->code, 'order' => 1, 'academic_year_id' => $year->id]);
        $classA = ClassGroup::query()->create(['organization_id' => $organization->id, 'name' => 'A '.$organization->code, 'academic_year_id' => $year->id, 'grade_id' => $grade->id]);
        $classB = ClassGroup::query()->create(['organization_id' => $organization->id, 'name' => 'B '.$organization->code, 'academic_year_id' => $year->id, 'grade_id' => $grade->id]);

        return [$year, $grade, $classA, $classB];
    }

    /** @return array{Organization, User} */
    private function member(string $code, string $roleName): array
    {
        $this->seed(LearnersPermissionSeeder::class);
        $organization = Organization::query()->create(['name' => $code, 'code' => $code, 'type' => 'school']);
        OrganizationModule::query()->create(['organization_id' => $organization->id, 'module_name' => 'learners', 'enabled' => true]);
        $user = User::factory()->create();
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active', 'is_default' => true]);

        return [$organization, $user];
    }
}
