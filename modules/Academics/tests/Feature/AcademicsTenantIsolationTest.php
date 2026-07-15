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
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Curriculum;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Learners\Database\Seeders\LearnersPermissionSeeder;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Infrastructure\Models\OrganizationModule;
use Tests\TestCase;

final class AcademicsTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_api_scopes_lists_bindings_creation_and_relationships(): void
    {
        [$first, $admin] = $this->member('first');
        [$second] = $this->member('second');
        $own = Curriculum::create(['organization_id' => $first->id, 'name' => 'Own', 'code' => 'SHARED']);
        $foreign = Curriculum::create(['organization_id' => $second->id, 'name' => 'Foreign', 'code' => 'SHARED']);

        $api = $this->actingAs($admin, 'sanctum')->withHeader('X-Organization-Id', $first->id);
        $response = $api->getJson('/api/v1/academics/curricula')->assertOk();
        $this->assertSame([$own->id], collect($response->json('data'))->pluck('id')->all());
        $api->getJson("/api/v1/academics/curricula/{$foreign->id}")->assertNotFound();
        $api->postJson('/api/v1/academics/curricula', ['organization_id' => $second->id, 'name' => 'Forged', 'code' => 'FORGED'])
            ->assertUnprocessable()->assertJsonValidationErrors('organization_id');
        $created = $api->postJson('/api/v1/academics/curricula', ['name' => 'Created', 'code' => 'CREATED'])->assertCreated()->json('data.id');
        $this->assertDatabaseHas('academics_curricula', ['id' => $created, 'organization_id' => $first->id]);

        $this->app['request']->attributes->remove('organization');
        $foreignYear = AcademicYear::create(['organization_id' => $second->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-01']);
        $foreignGrade = Grade::create(['organization_id' => $second->id, 'name' => 'Grade 8', 'order' => 8, 'academic_year_id' => $foreignYear->id]);
        $api->postJson('/api/v1/academics/classes', ['name' => 'Forged', 'academic_year_id' => $foreignYear->id, 'grade_id' => $foreignGrade->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['academic_year_id', 'grade_id']);
    }

    public function test_inactive_membership_and_suspended_organization_are_denied(): void
    {
        [$organization, $admin, $membership] = $this->member('state');
        $url = '/api/v1/academics/academic-years';
        $membership->update(['status' => 'suspended']);
        $this->actingAs($admin, 'sanctum')->withHeader('X-Organization-Id', $organization->id)->getJson($url)->assertForbidden();
        $membership->update(['status' => 'active']);
        $organization->update(['status' => 'suspended']);
        $this->actingAs($admin, 'sanctum')->withHeader('X-Organization-Id', $organization->id)->getJson($url)->assertForbidden();
    }

    public function test_learner_placement_rejects_every_foreign_academic_reference(): void
    {
        [$first, $admin] = $this->member('learner');
        [$second] = $this->member('foreign');
        $learner = LearnerProfile::factory()->create(['organization_id' => $first->id]);
        $curriculum = Curriculum::create(['organization_id' => $second->id, 'name' => 'Foreign', 'code' => 'FOREIGN']);
        $year = AcademicYear::create(['organization_id' => $second->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-01']);
        $grade = Grade::create(['organization_id' => $second->id, 'name' => 'Grade 8', 'order' => 8, 'academic_year_id' => $year->id, 'curriculum_id' => $curriculum->id]);
        $class = ClassGroup::create(['organization_id' => $second->id, 'name' => '8A', 'academic_year_id' => $year->id, 'grade_id' => $grade->id]);

        $this->actingAs($admin, 'sanctum')->withHeader('X-Organization-Id', $first->id)
            ->patchJson("/api/v1/learners/{$learner->uuid}/academic-placement", [
                'current_academic_year_id' => $year->id, 'current_grade_id' => $grade->id,
                'current_class_id' => $class->id, 'curriculum_id' => $curriculum->id,
            ])->assertUnprocessable()->assertJsonValidationErrors([
                'current_academic_year_id', 'current_grade_id', 'current_class_id', 'curriculum_id',
            ]);

        $ownCurriculum = Curriculum::create(['organization_id' => $first->id, 'name' => 'Own', 'code' => 'OWN']);
        $ownYear = AcademicYear::create(['organization_id' => $first->id, 'name' => '2027', 'start_date' => '2027-01-01', 'end_date' => '2027-12-01']);
        $ownGrade = Grade::create(['organization_id' => $first->id, 'name' => 'Grade 9', 'order' => 9, 'academic_year_id' => $ownYear->id, 'curriculum_id' => $ownCurriculum->id]);
        $ownClass = ClassGroup::create(['organization_id' => $first->id, 'name' => '9A', 'academic_year_id' => $ownYear->id, 'grade_id' => $ownGrade->id]);
        $this->actingAs($admin, 'sanctum')->withHeader('X-Organization-Id', $first->id)
            ->patchJson("/api/v1/learners/{$learner->uuid}/academic-placement", [
                'current_academic_year_id' => $ownYear->id, 'current_grade_id' => $ownGrade->id,
                'current_class_id' => $ownClass->id, 'curriculum_id' => $ownCurriculum->id,
            ])->assertOk()->assertJsonPath('data.academic_placement.class.id', $ownClass->id);
    }

    private function member(string $suffix): array
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(AcademicsPermissionSeeder::class);
        $this->seed(LearnersPermissionSeeder::class);
        $organization = Organization::create(['name' => "School {$suffix}", 'code' => "school-{$suffix}", 'type' => 'school']);
        OrganizationModule::create(['organization_id' => $organization->id, 'module_name' => 'academics', 'enabled' => true]);
        OrganizationModule::create(['organization_id' => $organization->id, 'module_name' => 'learners', 'enabled' => true]);
        $role = Role::firstOrCreate(['name' => "Tenant Admin {$suffix}"], ['is_system' => false]);
        $role->permissions()->syncWithoutDetaching(Permission::query()->where(fn ($query) => $query->where('name', 'like', 'academics.%')->orWhere('name', 'like', 'learners.%'))->pluck('id'));
        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        $membership = Membership::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active', 'is_default' => true]);

        return [$organization, $user, $membership];
    }
}
