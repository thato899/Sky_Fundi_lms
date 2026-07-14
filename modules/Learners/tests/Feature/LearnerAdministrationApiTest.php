<?php

declare(strict_types=1);

namespace Modules\Learners\Tests\Feature;

use Core\Identity\Infrastructure\Models\Membership;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Learners\Database\Seeders\LearnersPermissionSeeder;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Infrastructure\Models\OrganizationModule;
use Tests\TestCase;

final class LearnerAdministrationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_list_show_update_transition_archive_restore_and_view_history(): void
    {
        [$organization, $admin] = $this->member('api-admin', 'Organization Administrator');
        $headers = ['X-Organization-Id' => $organization->id];

        $created = $this->actingAs($admin, 'sanctum')->withHeaders($headers)->postJson('/api/v1/learners', [
            'first_name' => 'Lebo', 'last_name' => 'Khumalo', 'admission_number' => 'ADM-1',
        ])->assertCreated()->assertJsonPath('data.portal_access_enabled', false);
        $uuid = $created->json('data.uuid');

        $this->withHeaders($headers)->getJson('/api/v1/learners?search=ADM-1&sort=first_name&direction=desc&per_page=10')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.per_page', 10);
        $this->withHeaders($headers)->getJson("/api/v1/learners/{$uuid}")->assertOk()->assertJsonPath('data.first_name', 'Lebo');
        $this->withHeaders($headers)->patchJson("/api/v1/learners/{$uuid}", ['preferred_name' => 'Bee'])->assertOk()->assertJsonPath('data.preferred_name', 'Bee');
        $this->withHeaders($headers)->postJson("/api/v1/learners/{$uuid}/status", ['status' => 'admitted', 'reason' => 'Accepted'])->assertOk()->assertJsonPath('data.learner_status', 'admitted');
        $this->withHeaders($headers)->postJson("/api/v1/learners/{$uuid}/archive", ['reason' => 'Archived'])->assertOk()->assertJsonPath('data.archived', true);
        $this->withHeaders($headers)->postJson("/api/v1/learners/{$uuid}/restore", ['reason' => 'Restored'])->assertOk()->assertJsonPath('data.learner_status', 'admitted');
        $this->withHeaders($headers)->getJson("/api/v1/learners/{$uuid}/status-history")
            ->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('data.0.previous_status', 'archived');
    }

    public function test_api_enforces_auth_membership_organization_state_permissions_scoped_uuid_and_manual_override(): void
    {
        [$organization, $admin] = $this->member('secure-a', 'Academic Administrator');
        [$other] = $this->member('secure-b', 'Organization Administrator');
        $foreign = LearnerProfile::factory()->create(['organization_id' => $other->id]);

        $this->getJson('/api/v1/learners')->assertUnauthorized();
        $this->actingAs($admin, 'sanctum')->withHeader('X-Organization-Id', $organization->id)
            ->getJson("/api/v1/learners/{$foreign->uuid}")->assertNotFound();
        $this->withHeader('X-Organization-Id', $organization->id)->postJson('/api/v1/learners', [
            'first_name' => 'Manual', 'last_name' => 'Denied', 'learner_number' => 'MAN-API-1',
        ])->assertUnprocessable();

        [$teacherOrg, $teacher] = $this->member('teacher-org', 'Teacher');
        $this->actingAs($teacher, 'sanctum')->withHeader('X-Organization-Id', $teacherOrg->id)->getJson('/api/v1/learners')->assertForbidden();

        $organization->update(['status' => 'suspended']);
        $this->actingAs($admin, 'sanctum')->withHeader('X-Organization-Id', $organization->id)->getJson('/api/v1/learners')->assertForbidden();
    }

    public function test_invalid_directory_sort_and_payload_are_rejected(): void
    {
        [$organization, $admin] = $this->member('validation', 'Organization Administrator');
        $this->actingAs($admin, 'sanctum')->withHeader('X-Organization-Id', $organization->id)
            ->getJson('/api/v1/learners?sort=password&per_page=1000')->assertUnprocessable();
        $this->postJson('/api/v1/learners', ['organization_id' => $organization->id, 'first_name' => '', 'last_name' => 'Test'])
            ->assertUnprocessable()->assertJsonValidationErrors(['organization_id', 'first_name']);
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
