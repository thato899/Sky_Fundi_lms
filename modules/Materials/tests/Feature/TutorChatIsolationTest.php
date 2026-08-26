<?php

declare(strict_types=1);

namespace Modules\Materials\Tests\Feature;

use Core\Identity\Infrastructure\Models\Membership;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Materials\Application\MaterialRetrievalService;
use Modules\Materials\Database\Seeders\MaterialsPermissionSeeder;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Infrastructure\Models\OrganizationModule;
use Tests\TestCase;

/**
 * Explicit isolation coverage named in the brief: "a learner in org A
 * cannot retrieve org B's material." See docs/adr/011-materials-retrieval.md
 * and Application\TutorChatService/MaterialRetrievalService's docblocks
 * for the design this proves.
 */
final class TutorChatIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_learner_in_one_organization_is_never_answered_from_another_organizations_material(): void
    {
        Http::fake([
            'https://api.gemini.test/models/text-embedding-test:embedContent' => Http::response(['embedding' => ['values' => [1.0, 0.0]]]),
            'https://api.gemini.test/models/gemini-test:generateContent' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => "I don't have any course material to answer that from yet."]]]]],
            ]),
        ]);

        // Org B has real, embedded material about a topic.
        [$organizationB, $adminB] = $this->staffMember('isolation-org-b');
        $this->actingAs($adminB, 'sanctum')->withHeader('X-Organization-Id', $organizationB->id)
            ->postJson('/api/v1/materials', ['title' => 'Org B secret syllabus', 'content' => str_repeat('This is confidential material belonging only to organization B. ', 5)])
            ->assertCreated();

        // A learner belongs only to org A, which has no material at all.
        [$organizationA, $learnerUser] = $this->learnerMember('isolation-org-a');

        $response = $this->actingAs($learnerUser, 'sanctum')->withHeader('X-Organization-Id', $organizationA->id)
            ->postJson('/api/v1/materials/tutor-chat', ['question' => 'Tell me about the confidential material.'])
            ->assertOk();

        $this->assertFalse($response->json('data.grounded'));
        $this->assertSame([], $response->json('data.citations'));
        $this->assertStringNotContainsString('organization B', (string) $response->json('data.answer'));

        // The learner has no membership in org B at all — selecting its
        // context outright fails, the same isolation Billing/Learners
        // invitation tests already rely on.
        $this->actingAs($learnerUser, 'sanctum')->withHeader('X-Organization-Id', $organizationB->id)
            ->postJson('/api/v1/materials/tutor-chat', ['question' => 'Tell me about the confidential material.'])
            ->assertForbidden();
    }

    public function test_retrieval_service_never_loads_a_chunk_outside_the_requested_organization(): void
    {
        Http::fake(['https://api.gemini.test/models/text-embedding-test:embedContent' => Http::response(['embedding' => ['values' => [1.0, 0.0]]])]);
        [$organizationB, $adminB] = $this->staffMember('isolation-direct-b');
        $this->actingAs($adminB, 'sanctum')->withHeader('X-Organization-Id', $organizationB->id)
            ->postJson('/api/v1/materials', ['title' => 'Org B material', 'content' => str_repeat('Only organization B may see this content. ', 5)])
            ->assertCreated();
        [$organizationA] = $this->staffMember('isolation-direct-a');

        $results = app(MaterialRetrievalService::class)->topK(
            Organization::query()->findOrFail($organizationA->id),
            'organization B content',
        );

        $this->assertTrue($results->isEmpty());
    }

    /**
     * @return array{0: Organization, 1: User}
     */
    private function staffMember(string $code): array
    {
        $this->seed(MaterialsPermissionSeeder::class);
        $organization = Organization::query()->create(['name' => $code, 'code' => $code, 'type' => 'school']);
        OrganizationModule::query()->create(['organization_id' => $organization->id, 'module_name' => 'materials', 'enabled' => true]);
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(['name' => 'Organization Administrator'], ['is_system' => false]);
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active', 'is_default' => true]);

        return [$organization, $user];
    }

    /**
     * @return array{0: Organization, 1: User}
     */
    private function learnerMember(string $code): array
    {
        $organization = Organization::query()->create(['name' => $code, 'code' => $code, 'type' => 'school']);
        OrganizationModule::query()->create(['organization_id' => $organization->id, 'module_name' => 'materials', 'enabled' => true]);
        $role = Role::query()->firstOrCreate(['name' => 'Learner'], ['is_system' => false]);
        $user = User::factory()->create();
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
        LearnerProfile::factory()->active()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'portal_access_enabled' => true,
        ]);

        return [$organization, $user];
    }
}
