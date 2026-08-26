<?php

declare(strict_types=1);

namespace Modules\Materials\Tests\Feature;

use Core\Identity\Infrastructure\Models\Membership;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Materials\Application\MaterialRetrievalService;
use Modules\Materials\Database\Seeders\MaterialsPermissionSeeder;
use Modules\Materials\Domain\Enums\MaterialStatus;
use Modules\Materials\Infrastructure\Models\Material;
use Modules\Materials\Infrastructure\Models\MaterialChunk;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Infrastructure\Models\OrganizationModule;
use Tests\TestCase;

/**
 * QUEUE_CONNECTION=sync in phpunit.xml, so ChunkAndEmbedMaterialJob
 * runs inline within the ingest() call — no queue faking needed, only
 * Http::fake() for the embedding calls it makes.
 */
final class MaterialIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingestion_chunks_and_embeds_material_synchronously_via_the_queued_job(): void
    {
        Http::fake(['https://api.gemini.test/models/text-embedding-test:embedContent' => Http::sequence()
            ->push(['embedding' => ['values' => [1.0, 0.0, 0.0]]])
            ->push(['embedding' => ['values' => [0.0, 1.0, 0.0]]])]);
        [$organization, $admin] = $this->member('ingest-happy');

        $content = str_repeat('Photosynthesis converts light energy into chemical energy. ', 4)
            ."\n\n".str_repeat('The Krebs cycle occurs in the mitochondrial matrix. ', 6);
        $response = $this->actingAs($admin, 'sanctum')
            ->withHeader('X-Organization-Id', $organization->id)
            ->postJson('/api/v1/materials', ['title' => 'Cell Biology Notes', 'content' => $content])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Cell Biology Notes')
            ->assertJsonPath('data.status', 'pending');

        $material = Material::query()->where('uuid', $response->json('data.id'))->firstOrFail();
        $this->assertSame(MaterialStatus::Ready, $material->status);
        $chunks = MaterialChunk::query()->where('material_id', $material->id)->orderBy('chunk_index')->get();
        $this->assertGreaterThanOrEqual(1, $chunks->count());
        foreach ($chunks as $chunk) {
            $this->assertNotEmpty($chunk->embedding);
            $this->assertSame($organization->id, $chunk->organization_id);
        }
    }

    public function test_ingestion_marks_material_failed_when_embedding_fails(): void
    {
        Http::fake(['https://api.gemini.test/models/text-embedding-test:embedContent' => Http::response('server error', 500)]);
        [$organization, $admin] = $this->member('ingest-failure');

        $response = $this->actingAs($admin, 'sanctum')
            ->withHeader('X-Organization-Id', $organization->id)
            ->postJson('/api/v1/materials', ['title' => 'Broken Upload', 'content' => str_repeat('Some real content here. ', 5)])
            ->assertCreated();

        $material = Material::query()->where('uuid', $response->json('data.id'))->firstOrFail();
        $this->assertSame(MaterialStatus::Failed, $material->status);
        $this->assertNotNull($material->failure_message);
        $this->assertSame(0, MaterialChunk::query()->where('material_id', $material->id)->count());
    }

    public function test_content_below_the_minimum_length_is_rejected(): void
    {
        [$organization, $admin] = $this->member('ingest-too-short');

        $this->actingAs($admin, 'sanctum')
            ->withHeader('X-Organization-Id', $organization->id)
            ->postJson('/api/v1/materials', ['title' => 'Too short', 'content' => 'Nope.'])
            ->assertUnprocessable();
    }

    public function test_member_without_upload_permission_is_forbidden(): void
    {
        $this->seed(MaterialsPermissionSeeder::class);
        $organization = Organization::query()->create(['name' => 'ingest-forbidden', 'code' => 'ingest-forbidden', 'type' => 'school']);
        OrganizationModule::query()->create(['organization_id' => $organization->id, 'module_name' => 'materials', 'enabled' => true]);
        $role = Role::query()->firstOrCreate(['name' => 'Learner'], ['is_system' => false]);
        $user = User::factory()->create();
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active', 'is_default' => true]);

        $this->actingAs($user, 'sanctum')
            ->withHeader('X-Organization-Id', $organization->id)
            ->postJson('/api/v1/materials', ['title' => 'Not allowed', 'content' => str_repeat('Content. ', 10)])
            ->assertForbidden();

        $this->assertSame(0, Material::query()->count());
    }

    public function test_retrieval_only_returns_chunks_scoped_to_the_organization(): void
    {
        Http::fake(['https://api.gemini.test/models/text-embedding-test:embedContent' => Http::sequence()
            ->push(['embedding' => ['values' => [1.0, 0.0]]]) // org A material chunk
            ->push(['embedding' => ['values' => [1.0, 0.0]]]), // query embedding
        ]);
        [$organizationA, $adminA] = $this->member('retrieval-a');
        [$organizationB] = $this->member('retrieval-b');

        $this->actingAs($adminA, 'sanctum')->withHeader('X-Organization-Id', $organizationA->id)
            ->postJson('/api/v1/materials', ['title' => 'Org A material', 'content' => str_repeat('Only visible to organization A. ', 5)])
            ->assertCreated();

        $organization = Organization::query()->find($organizationB->id);
        $results = app(MaterialRetrievalService::class)->topK($organization, 'anything');
        $this->assertTrue($results->isEmpty());
        Http::assertSentCount(2);
    }

    /**
     * @return array{0: Organization, 1: User}
     */
    private function member(string $code): array
    {
        $this->seed(MaterialsPermissionSeeder::class);
        $organization = Organization::query()->create(['name' => $code, 'code' => $code, 'type' => 'school']);
        OrganizationModule::query()->create(['organization_id' => $organization->id, 'module_name' => 'materials', 'enabled' => true]);
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(['name' => 'Organization Administrator'], ['is_system' => false]);
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active', 'is_default' => true]);

        return [$organization, $user];
    }
}
