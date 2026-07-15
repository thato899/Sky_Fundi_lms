<?php

declare(strict_types=1);

namespace Modules\Learners\Tests\Feature;

use Core\Identity\Infrastructure\Models\Membership;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academics\Domain\Enums\AcademicStatus;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Curriculum;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Learners\Database\Seeders\LearnersPermissionSeeder;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Infrastructure\Models\OrganizationModule;
use Tests\TestCase;

final class LearnerManagementWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_search_filters_sorting_pagination_and_tenant_isolation(): void
    {
        [$organization, $admin, $membership] = $this->member('web-directory', 'Organization Administrator');
        [$other] = $this->member('web-private', 'Organization Administrator');
        LearnerProfile::factory()->create(['organization_id' => $organization->id, 'learner_number' => 'LRN-000002', 'first_name' => 'Zola', 'last_name' => 'Smith', 'learner_status' => 'active']);
        LearnerProfile::factory()->create(['organization_id' => $organization->id, 'learner_number' => 'LRN-000001', 'first_name' => 'Amy', 'last_name' => 'Jones', 'learner_status' => 'archived', 'archived_at' => now()]);
        LearnerProfile::factory()->create(['organization_id' => $other->id, 'learner_number' => 'PRIVATE-1', 'first_name' => 'Private']);
        $session = $this->actingAs($admin)->withSession(['organization_id' => $membership->organization_id]);

        $session->get(route('learners.index'))->assertOk()->assertSee('LRN-000002')->assertDontSee('LRN-000001')->assertDontSee('PRIVATE-1');
        $session->get(route('learners.index', ['search' => 'Zola']))->assertSee('LRN-000002');
        $session->get(route('learners.index', ['archived' => '1']))->assertSee('LRN-000001')->assertDontSee('LRN-000002');
        $session->get(route('learners.index', ['learner_status' => 'active', 'sort' => 'last_name', 'direction' => 'desc']))->assertSee('Zola');
        foreach (range(3, 30) as $number) {
            LearnerProfile::factory()->create(['organization_id' => $organization->id, 'learner_number' => sprintf('LRN-%06d', $number)]);
        }
        $session->get(route('learners.index', ['search' => 'LRN', 'per_page' => 10]))->assertSee('Page 1 of 3')->assertSee('Next')->assertSee('search=LRN');
    }

    public function test_profile_only_creation_generation_manual_override_and_validation(): void
    {
        [$organization, $admin, $membership] = $this->member('web-create', 'Organization Administrator');
        $session = $this->actingAs($admin)->withSession(['organization_id' => $membership->organization_id]);
        $session->get(route('learners.create'))->assertOk()->assertSee('Manual learner number');
        $session->post(route('learners.store'), ['first_name' => 'Lebo', 'last_name' => 'Khumalo', 'admission_number' => 'ADM-1'])->assertRedirect();
        $learner = LearnerProfile::query()->where('organization_id', $organization->id)->firstOrFail();
        $this->assertSame('LRN-000001', $learner->learner_number);
        $this->assertNull($learner->user_id);
        $this->assertNull($learner->organization_membership_id);
        $this->assertFalse($learner->portal_access_enabled);
        $this->assertDatabaseHas('audit_logs', ['action' => 'learners.created', 'target_id' => $learner->id]);
        $session->post(route('learners.store'), ['first_name' => 'Manual', 'last_name' => 'Number', 'learner_number' => 'MAN-100'])->assertRedirect();
        $this->assertDatabaseHas('learner_profiles', ['organization_id' => $organization->id, 'learner_number' => 'MAN-100']);
        $session->from(route('learners.create'))->post(route('learners.store'), ['first_name' => '', 'last_name' => '', 'organization_id' => $organization->id])->assertSessionHasErrors(['first_name', 'last_name', 'organization_id']);
        $session->post(route('learners.store'), ['first_name' => 'Duplicate', 'last_name' => 'Number', 'learner_number' => 'MAN-100'])->assertSessionHasErrors('learner');
    }

    public function test_manual_number_is_hidden_and_denied_without_override_permission(): void
    {
        [$organization, $admin, $membership] = $this->member('web-academic', 'Academic Administrator');
        $session = $this->actingAs($admin)->withSession(['organization_id' => $membership->organization_id]);
        $session->get(route('learners.create'))->assertOk()->assertDontSee('Manual learner number');
        $session->post(route('learners.store'), ['first_name' => 'No', 'last_name' => 'Override', 'learner_number' => 'DENIED-1'])->assertSessionHasErrors('learner');
        $this->assertDatabaseMissing('learner_profiles', ['organization_id' => $organization->id, 'learner_number' => 'DENIED-1']);
    }

    public function test_profile_update_status_archive_restore_history_and_safe_rendering(): void
    {
        [$organization, $admin, $membership] = $this->member('web-workflow', 'Organization Administrator');
        $learner = LearnerProfile::factory()->create(['organization_id' => $organization->id, 'learner_status' => 'pending', 'metadata' => ['secret' => 'DO-NOT-RENDER']]);
        $session = $this->actingAs($admin)->withSession(['organization_id' => $membership->organization_id]);
        $session->get(route('learners.show', $learner->uuid))->assertOk()->assertSee($learner->learner_number)->assertSee('Profile only')->assertDontSee('DO-NOT-RENDER');
        $session->put(route('learners.update', $learner->uuid), ['first_name' => 'Updated', 'last_name' => 'Learner', 'organization_id' => 'forged', 'learner_number' => 'FORGED'])->assertSessionHasErrors(['organization_id', 'learner_number']);
        $session->put(route('learners.update', $learner->uuid), ['first_name' => 'Updated', 'last_name' => 'Learner'])->assertRedirect(route('learners.show', $learner->uuid));
        $session->post(route('learners.status', $learner->uuid), ['status' => 'admitted', 'reason' => 'Accepted'])->assertRedirect();
        $session->post(route('learners.status', $learner->uuid), ['status' => 'admitted'])->assertRedirect();
        $session->post(route('learners.archive', $learner->uuid), ['reason' => 'Left'])->assertRedirect();
        $session->get(route('learners.archive', $learner->uuid))->assertStatus(405);
        $session->post(route('learners.restore', $learner->uuid), ['reason' => 'Returned'])->assertRedirect();
        $this->assertSame('admitted', $learner->fresh()->learner_status->value);
        $this->assertDatabaseCount('learner_status_histories', 3);
        $session->get(route('learners.show', $learner->uuid))->assertSee('Accepted')->assertSee('Left')->assertSee('Returned');
    }

    public function test_academic_placement_is_organization_scoped_and_compatible(): void
    {
        [$organization, $admin, $membership] = $this->member('web-placement', 'Organization Administrator');
        [$other] = $this->member('web-placement-other', 'Organization Administrator');
        [$year, $curriculum, $grade, $class] = $this->academics($organization);
        [$foreignYear, $foreignCurriculum, $foreignGrade, $foreignClass] = $this->academics($other);
        $learner = LearnerProfile::factory()->create(['organization_id' => $organization->id]);
        $session = $this->actingAs($admin)->withSession(['organization_id' => $membership->organization_id]);
        $session->get(route('learners.academic-placement.edit', $learner->uuid))->assertOk()->assertSee($year->name)->assertDontSee($foreignYear->name);
        $session->put(route('learners.academic-placement.update', $learner->uuid), ['current_academic_year_id' => $year->id, 'curriculum_id' => $curriculum->id, 'current_grade_id' => $grade->id, 'current_class_id' => $class->id])->assertRedirect();
        $this->assertSame($class->id, $learner->fresh()->current_class_id);
        foreach (['current_academic_year_id' => $foreignYear->id, 'curriculum_id' => $foreignCurriculum->id, 'current_grade_id' => $foreignGrade->id, 'current_class_id' => $foreignClass->id] as $field => $id) {
            $session->from(route('learners.academic-placement.edit', $learner->uuid))->put(route('learners.academic-placement.update', $learner->uuid), [$field => $id])->assertSessionHasErrors($field);
        }
    }

    public function test_auth_permission_inactive_context_and_foreign_uuid_are_protected(): void
    {
        [$organization, $admin, $membership] = $this->member('web-secure', 'Organization Administrator');
        [$other] = $this->member('web-secure-other', 'Organization Administrator');
        $foreign = LearnerProfile::factory()->create(['organization_id' => $other->id]);
        $this->get(route('learners.index'))->assertRedirect(route('login'));
        $this->actingAs($admin)->withSession(['organization_id' => $membership->organization_id])->get(route('learners.show', $foreign->uuid))->assertNotFound();
        $this->get(route('learners.index', ['organization_id' => $other->id]))->assertOk()->assertDontSee($foreign->learner_number);
        $membership->update(['status' => 'suspended']);
        $this->get(route('learners.index'))->assertForbidden();
    }

    /** @return array{Organization, User, Membership} */
    private function member(string $code, string $roleName): array
    {
        $this->seed(LearnersPermissionSeeder::class);
        $organization = Organization::query()->create(['name' => $code, 'code' => $code, 'type' => 'school']);
        OrganizationModule::query()->create(['organization_id' => $organization->id, 'module_name' => 'learners', 'enabled' => true]);
        $user = User::factory()->create();
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $membership = Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active', 'is_default' => true]);

        return [$organization, $user, $membership];
    }

    private function academics(Organization $organization): array
    {
        $year = AcademicYear::query()->create(['organization_id' => $organization->id, 'name' => $organization->code.' 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'current']);
        $curriculum = Curriculum::query()->create(['organization_id' => $organization->id, 'name' => $organization->code.' Curriculum', 'code' => $organization->code.'-CUR', 'is_active' => true]);
        $grade = Grade::query()->create(['organization_id' => $organization->id, 'academic_year_id' => $year->id, 'curriculum_id' => $curriculum->id, 'name' => $organization->code.' Grade', 'order' => 1, 'status' => AcademicStatus::Active]);
        $class = ClassGroup::query()->create(['organization_id' => $organization->id, 'academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => $organization->code.' Class', 'capacity' => 30, 'is_homeroom' => true, 'status' => AcademicStatus::Active]);

        return [$year, $curriculum, $grade, $class];
    }
}
