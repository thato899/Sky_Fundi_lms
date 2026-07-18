<?php

declare(strict_types=1);

namespace Modules\Learners\Tests\Feature;

use Core\Identity\Infrastructure\Models\Membership;
use Core\Licensing\Domain\Enums\LicenseStatus;
use Core\Licensing\Domain\Enums\LicenseTier;
use Core\Licensing\Infrastructure\Models\License;
use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Learners\Application\GuardianService;
use Modules\Learners\Application\LearnerService;
use Modules\Learners\Database\Seeders\LearnersPermissionSeeder;
use Modules\Learners\Infrastructure\Models\GuardianProfile;
use Modules\Learners\Infrastructure\Models\LearnerConsent;
use Modules\Learners\Infrastructure\Models\LearnerGuardianRelationship;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Infrastructure\Models\OrganizationModule;
use Tests\TestCase;

final class GuardianVerticalSliceTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_complete_guardian_relationship_consent_and_archive_api_workflow(): void
    {
        [$organization, $admin] = $this->member('guardian-api', 'Organization Administrator');
        $learner = LearnerProfile::factory()->create(['organization_id' => $organization->id]);
        $headers = ['X-Organization-Id' => $organization->id];
        $this->actingAs($admin, 'sanctum')->withHeaders($headers);

        $guardianResponse = $this->postJson('/api/v1/guardians', ['first_name' => 'Mpho', 'last_name' => 'Dube', 'email' => 'mpho@example.test', 'preferred_communication_channel' => 'email'])
            ->assertCreated()->assertJsonPath('data.identity_linked', false);
        $guardianUuid = $guardianResponse->json('data.uuid');

        $relationship = $this->postJson("/api/v1/learners/{$learner->uuid}/guardians", ['guardian_uuid' => $guardianUuid, 'relationship_type' => 'parent', 'is_primary' => true, 'is_emergency_contact' => true])
            ->assertCreated()->assertJsonPath('data.is_primary', true);
        $relationshipUuid = $relationship->json('data.uuid');

        $this->postJson("/api/v1/learners/{$learner->uuid}/guardians/consents", ['guardian_uuid' => $guardianUuid, 'consent_type' => 'media', 'status' => 'granted', 'recorded_date' => now()->toDateString()])
            ->assertCreated()->assertJsonPath('data.status', 'granted');
        $this->patchJson("/api/v1/learners/{$learner->uuid}/guardians/{$relationshipUuid}", ['is_primary' => true, 'is_authorized_pickup' => true])
            ->assertOk()->assertJsonPath('data.is_authorized_pickup', true);
        $this->getJson("/api/v1/learners/{$learner->uuid}/guardians")->assertOk()->assertJsonCount(1, 'data');
        $this->postJson("/api/v1/guardians/{$guardianUuid}/archive")->assertOk()->assertJsonPath('data.status', 'archived');

        $this->assertDatabaseHas('learner_consents', ['learner_profile_id' => $learner->id, 'status' => 'granted']);
        $this->assertDatabaseHas('learner_guardian_relationships', ['learner_profile_id' => $learner->id, 'status' => 'inactive']);
    }

    public function test_relationships_are_unique_primary_is_reassigned_and_cross_organization_links_are_rejected(): void
    {
        [$organization, $actor] = $this->member('rules', 'Organization Administrator');
        [$other] = $this->member('rules-other', 'Organization Administrator');
        $learner = LearnerProfile::factory()->create(['organization_id' => $organization->id]);
        $first = GuardianProfile::query()->create(['organization_id' => $organization->id, 'first_name' => 'First', 'last_name' => 'One', 'preferred_communication_channel' => 'email']);
        $second = GuardianProfile::query()->create(['organization_id' => $organization->id, 'first_name' => 'Second', 'last_name' => 'Two', 'preferred_communication_channel' => 'sms']);
        $foreign = GuardianProfile::query()->create(['organization_id' => $other->id, 'first_name' => 'Foreign', 'last_name' => 'Guardian', 'preferred_communication_channel' => 'none']);
        $service = app(GuardianService::class);

        $service->link($learner, $first, $actor, ['relationship_type' => 'parent', 'is_primary' => true]);
        $service->link($learner, $second, $actor, ['relationship_type' => 'caregiver', 'is_primary' => true]);
        $this->assertDatabaseHas('learner_guardian_relationships', ['guardian_profile_id' => $first->id, 'is_primary' => false]);
        $this->assertDatabaseHas('learner_guardian_relationships', ['guardian_profile_id' => $second->id, 'is_primary' => true]);

        $this->expectException(DomainException::class);
        $service->link($learner, $foreign, $actor, ['relationship_type' => 'other']);
    }

    public function test_linked_guardian_identity_can_view_only_explicitly_linked_learner(): void
    {
        [$organization, $admin] = $this->member('portal', 'Organization Administrator');
        $guardianUser = User::factory()->create();
        $guardianMembership = Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $guardianUser->id, 'status' => 'active']);
        $guardian = GuardianProfile::query()->create(['organization_id' => $organization->id, 'user_id' => $guardianUser->id, 'organization_membership_id' => $guardianMembership->id, 'first_name' => 'Linked', 'last_name' => 'Guardian', 'preferred_communication_channel' => 'email']);
        $linked = LearnerProfile::factory()->active()->create(['organization_id' => $organization->id, 'residential_address' => 'Private learner address']);
        $unlinked = LearnerProfile::factory()->create(['organization_id' => $organization->id]);
        app(GuardianService::class)->link($linked, $guardian, $admin, ['relationship_type' => 'parent']);

        $this->actingAs($guardianUser, 'sanctum')->withHeader('X-Organization-Id', $organization->id)
            ->getJson("/api/v1/learners/{$linked->uuid}")
            ->assertOk()
            ->assertJsonMissing(['residential_address'])
            ->assertJsonMissing(['portal_access_enabled'])
            ->assertJsonMissing(['onboarding_status'])
            ->assertJsonMissing(['archived_at']);
        $this->getJson("/api/v1/learners/{$unlinked->uuid}")->assertForbidden();
        $this->getJson('/api/v1/learners')->assertForbidden();
    }

    public function test_learner_guardian_api_uses_management_and_portal_projections(): void
    {
        [$organization, $admin] = $this->member('guardian-api-privacy', 'Organization Administrator');
        $learner = LearnerProfile::factory()->active()->create(['organization_id' => $organization->id, 'residential_address' => 'Private learner residence']);
        $guardianUser = User::factory()->create();
        $guardianMembership = Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $guardianUser->id, 'status' => 'active']);
        $own = GuardianProfile::query()->create(['organization_id' => $organization->id, 'user_id' => $guardianUser->id, 'organization_membership_id' => $guardianMembership->id, 'first_name' => 'Portal', 'last_name' => 'Guardian']);
        $other = GuardianProfile::query()->create(['organization_id' => $organization->id, 'first_name' => 'Other', 'last_name' => 'Guardian']);
        app(GuardianService::class)->link($learner, $other, $admin, ['relationship_type' => 'caregiver']);
        app(GuardianService::class)->link($learner, $own, $admin, [
            'relationship_type' => 'parent',
            'is_primary' => true,
            'is_emergency_contact' => true,
            'is_authorized_pickup' => true,
            'receives_academic_communication' => true,
            'receives_financial_communication' => true,
        ]);
        $url = "/api/v1/learners/{$learner->uuid}/guardians";
        $headers = ['X-Organization-Id' => $organization->id];

        $this->actingAs($admin, 'sanctum')->withHeaders($headers)->getJson($url)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'is_primary' => true,
                'is_emergency_contact' => true,
                'is_authorized_pickup' => true,
                'receives_academic_communication' => true,
                'receives_financial_communication' => true,
            ]);

        $response = $this->actingAs($guardianUser, 'sanctum')->withHeaders($headers)->getJson($url)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.guardian.uuid', $own->uuid)
            ->assertJsonMissing(['is_primary'])
            ->assertJsonMissing(['is_emergency_contact'])
            ->assertJsonMissing(['is_authorized_pickup'])
            ->assertJsonMissing(['receives_academic_communication'])
            ->assertJsonMissing(['receives_financial_communication']);
        $this->assertStringNotContainsString($other->uuid, json_encode($response->json(), JSON_THROW_ON_ERROR));
    }

    public function test_learner_creation_enforces_configured_organization_licence_capacity(): void
    {
        [$organization, $actor] = $this->member('capacity', 'Organization Administrator');
        License::query()->create(['license_key' => 'CAPACITY-TEST-KEY', 'licensee_type' => Organization::class, 'licensee_id' => $organization->id, 'tier' => LicenseTier::Starter, 'status' => LicenseStatus::Active, 'max_learners' => 1]);
        $service = app(LearnerService::class);
        $service->create($organization, $actor, ['first_name' => 'One', 'last_name' => 'Learner'], false);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('licence limit');
        $service->create($organization, $actor, ['first_name' => 'Two', 'last_name' => 'Learner'], false);
    }

    public function test_administrator_web_journey_creates_and_links_guardian_then_views_learner(): void
    {
        [$organization, $admin] = $this->member('guardian-web', 'Organization Administrator');
        $learner = LearnerProfile::factory()->create(['organization_id' => $organization->id]);
        $session = ['active_organization_id' => $organization->id];

        $this->actingAs($admin)->withSession($session)->get('/guardians')->assertOk()->assertSee('Guardian management');
        $this->withSession($session)->post('/guardians', ['first_name' => 'Web', 'last_name' => 'Guardian', 'email' => 'web@example.test', 'preferred_communication_channel' => 'email'])->assertRedirect();
        $guardian = GuardianProfile::query()->where('organization_id', $organization->id)->where('email', 'web@example.test')->firstOrFail();
        $this->withSession($session)->post("/learners/{$learner->uuid}/guardians", ['guardian_uuid' => $guardian->uuid, 'relationship_type' => 'parent', 'is_primary' => '1', 'is_emergency_contact' => '1'])->assertRedirect();
        $this->withSession($session)->get("/learners/{$learner->uuid}")->assertOk()->assertSee('Web Guardian')->assertSee('Consent summary');
    }

    public function test_standard_database_seeder_installs_guardian_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(8, Permission::query()->whereIn('name', [
            'guardians.view', 'guardians.create', 'guardians.update', 'guardians.archive',
            'guardians.manage_relationships', 'guardians.invite',
            'guardians.view_invitations', 'guardians.revoke_invitations',
        ])->count());
    }

    public function test_guardian_portal_lifecycle_requires_active_current_relationship_and_visible_learner(): void
    {
        [$organization, $admin] = $this->member('portal-lifecycle', 'Organization Administrator');
        $guardianUser = User::factory()->create();
        $membership = Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $guardianUser->id, 'status' => 'active']);
        $guardian = GuardianProfile::query()->create(['organization_id' => $organization->id, 'user_id' => $guardianUser->id, 'organization_membership_id' => $membership->id, 'first_name' => 'Portal', 'last_name' => 'Guardian']);
        $learner = LearnerProfile::factory()->active()->create(['organization_id' => $organization->id]);
        $relationship = app(GuardianService::class)->link($learner, $guardian, $admin, ['relationship_type' => 'parent']);
        $headers = ['X-Organization-Id' => $organization->id];

        $this->actingAs($guardianUser, 'sanctum')->withHeaders($headers)->getJson("/api/v1/learners/{$learner->uuid}")->assertOk();
        $guardian->update(['status' => 'inactive']);
        $this->getJson("/api/v1/learners/{$learner->uuid}")->assertForbidden();
        $guardian->update(['status' => 'active', 'archived_at' => now()]);
        $this->getJson("/api/v1/learners/{$learner->uuid}")->assertForbidden();
        $guardian->update(['archived_at' => null]);
        $relationship->update(['effective_from' => today()->addDay()]);
        $this->getJson("/api/v1/learners/{$learner->uuid}")->assertForbidden();
        $relationship->update(['effective_from' => null, 'effective_until' => today()->subDay()]);
        $this->getJson("/api/v1/learners/{$learner->uuid}")->assertForbidden();
        $relationship->update(['effective_until' => null]);
        $learner->update(['learner_status' => 'archived', 'archived_at' => now()]);
        $this->getJson("/api/v1/learners/{$learner->uuid}")->assertForbidden();
    }

    public function test_linked_guardian_web_view_hides_other_guardians_and_consents(): void
    {
        [$organization, $admin] = $this->member('web-privacy', 'Organization Administrator');
        $learner = LearnerProfile::factory()->active()->create(['organization_id' => $organization->id]);
        $guardianUser = User::factory()->create();
        $membership = Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $guardianUser->id, 'status' => 'active']);
        $own = GuardianProfile::query()->create(['organization_id' => $organization->id, 'user_id' => $guardianUser->id, 'organization_membership_id' => $membership->id, 'first_name' => 'Own', 'last_name' => 'Guardian', 'email' => 'own@example.test']);
        $other = GuardianProfile::query()->create(['organization_id' => $organization->id, 'first_name' => 'Private', 'last_name' => 'Other', 'email' => 'private@example.test']);
        app(GuardianService::class)->link($learner, $own, $admin, ['relationship_type' => 'parent']);
        app(GuardianService::class)->link($learner, $other, $admin, ['relationship_type' => 'caregiver', 'is_emergency_contact' => true]);
        LearnerConsent::query()->create(['organization_id' => $organization->id, 'learner_profile_id' => $learner->id, 'guardian_profile_id' => $other->id, 'consent_type' => 'secret_consent', 'status' => 'granted', 'recorded_date' => today(), 'notes' => 'private consent note']);

        $session = ['active_organization_id' => $organization->id];
        $this->actingAs($guardianUser)->withSession($session)->get("/learners/{$learner->uuid}")
            ->assertOk()->assertSee('Own Guardian')->assertDontSee('Private Other')->assertDontSee('private@example.test')
            ->assertDontSee('Consent summary')->assertDontSee('secret consent')->assertDontSee('private consent note')->assertDontSee('Emergency')
            ->assertDontSee('Private learner residence')->assertDontSee('Portal access')->assertDontSee('Account state');
        $this->actingAs($admin)->withSession($session)->get("/learners/{$learner->uuid}")
            ->assertOk()->assertSee('Own Guardian')->assertSee('Private Other')->assertSee('Consent summary')->assertSee('Secret consent');
    }

    public function test_guardian_profile_web_view_uses_a_restricted_self_service_projection(): void
    {
        [$organization, $admin] = $this->member('guardian-profile-web', 'Organization Administrator');
        $guardianUser = User::factory()->create();
        $membership = Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $guardianUser->id, 'status' => 'active']);
        $guardian = GuardianProfile::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $guardianUser->id,
            'organization_membership_id' => $membership->id,
            'first_name' => 'Self',
            'last_name' => 'Service',
            'email' => 'self@example.test',
            'phone' => '0123456789',
            'address' => 'Private guardian address',
        ]);
        $learner = LearnerProfile::factory()->active()->create(['organization_id' => $organization->id, 'first_name' => 'Linked', 'last_name' => 'Learner']);
        app(GuardianService::class)->link($learner, $guardian, $admin, [
            'relationship_type' => 'parent',
            'is_primary' => true,
            'is_emergency_contact' => true,
            'is_authorized_pickup' => true,
            'receives_academic_communication' => true,
            'receives_financial_communication' => true,
        ]);
        $session = ['active_organization_id' => $organization->id];
        $url = "/guardians/{$guardian->uuid}";

        $this->actingAs($guardianUser)->withSession($session)->get($url)
            ->assertOk()
            ->assertSee('Self Service')
            ->assertSee('self@example.test')
            ->assertSee('Linked Learner')
            ->assertDontSee('Private guardian address')
            ->assertDontSee('Portal identity')
            ->assertDontSee('Invitation state')
            ->assertDontSee('primary contact')
            ->assertDontSee('Emergency')
            ->assertDontSee('Authorized pickup')
            ->assertDontSee('Academic communication')
            ->assertDontSee('Financial communication');

        $this->actingAs($admin)->withSession($session)->get($url)
            ->assertOk()
            ->assertSee('Private guardian address')
            ->assertSee('Portal identity')
            ->assertSee('Invitation state')
            ->assertSee('primary contact');
    }

    public function test_primary_updates_normalize_state_and_database_rejects_multiple_active_primaries(): void
    {
        [$organization, $actor] = $this->member('primary-rules', 'Organization Administrator');
        $learner = LearnerProfile::factory()->create(['organization_id' => $organization->id]);
        $first = GuardianProfile::query()->create(['organization_id' => $organization->id, 'first_name' => 'First', 'last_name' => 'Guardian']);
        $second = GuardianProfile::query()->create(['organization_id' => $organization->id, 'first_name' => 'Second', 'last_name' => 'Guardian']);
        $service = app(GuardianService::class);
        $one = $service->link($learner, $first, $actor, ['relationship_type' => 'parent', 'is_primary' => true]);
        $two = $service->link($learner, $second, $actor, ['relationship_type' => 'caregiver', 'status' => 'inactive', 'is_primary' => true]);
        $this->assertFalse($two->refresh()->is_primary);
        $service->updateRelationship($two, $actor, ['status' => 'active', 'is_primary' => true]);
        $this->assertFalse($one->refresh()->is_primary);
        $this->assertTrue($two->refresh()->is_primary);
        try {
            $one->forceFill(['status' => 'active', 'is_primary' => true])->save();
            $this->fail('The database must reject a second active primary guardian.');
        } catch (QueryException) {
            $this->assertTrue($two->refresh()->is_primary);
        }
        $service->updateRelationship($two, $actor, ['status' => 'inactive']);
        $this->assertFalse($two->refresh()->is_primary);
    }

    public function test_unlinked_relationship_can_be_relinked_without_duplicate_current_rows(): void
    {
        [$organization, $actor] = $this->member('relink', 'Organization Administrator');
        $learner = LearnerProfile::factory()->create(['organization_id' => $organization->id]);
        $guardian = GuardianProfile::query()->create(['organization_id' => $organization->id, 'first_name' => 'Relink', 'last_name' => 'Guardian']);
        $service = app(GuardianService::class);
        $relationship = $service->link($learner, $guardian, $actor, ['relationship_type' => 'parent']);
        $service->unlink($relationship, $actor);
        $relinked = $service->link($learner, $guardian, $actor, ['relationship_type' => 'legal_guardian']);

        $this->assertSame($relationship->id, $relinked->id);
        $this->assertSame(1, LearnerGuardianRelationship::query()->where('learner_profile_id', $learner->id)->where('status', 'active')->count());
    }

    public function test_relink_refreshes_historical_ownership_and_trusted_foreign_keys(): void
    {
        [$organization, $actor] = $this->member('relink-owner', 'Organization Administrator');
        [$other] = $this->member('relink-owner-other', 'Organization Administrator');
        $learner = LearnerProfile::factory()->create(['organization_id' => $organization->id]);
        $guardian = GuardianProfile::query()->create(['organization_id' => $organization->id, 'first_name' => 'Relink', 'last_name' => 'Owner']);
        $service = app(GuardianService::class);
        $relationship = $service->link($learner, $guardian, $actor, ['relationship_type' => 'parent', 'is_primary' => true]);
        $service->unlink($relationship, $actor);
        DB::table('learner_guardian_relationships')->where('id', $relationship->id)->update(['organization_id' => $other->id]);

        $relinked = $service->link($learner, $guardian, $actor, ['relationship_type' => 'legal_guardian', 'is_primary' => true]);

        $this->assertSame($relationship->id, $relinked->id);
        $this->assertSame($organization->id, $relinked->organization_id);
        $this->assertSame($learner->id, $relinked->learner_profile_id);
        $this->assertSame($guardian->id, $relinked->guardian_profile_id);
        $this->assertSame(1, LearnerGuardianRelationship::query()->where('organization_id', $organization->id)->where('learner_profile_id', $learner->id)->where('guardian_profile_id', $guardian->id)->where('status', 'active')->count());
        $this->assertSame(0, LearnerGuardianRelationship::query()->where('organization_id', $other->id)->whereKey($relationship->id)->count());

        $service->unlink($relinked, $actor);
        $guardian->update(['status' => 'inactive']);
        $this->expectException(DomainException::class);
        $service->link($learner, $guardian, $actor, ['relationship_type' => 'parent', 'status' => 'active']);
    }

    public function test_guardian_filters_and_collection_serialization_are_bounded(): void
    {
        [$organization, $admin] = $this->member('guardian-filters', 'Organization Administrator');
        GuardianProfile::query()->create(['organization_id' => $organization->id, 'first_name' => 'Active', 'last_name' => 'One']);
        GuardianProfile::query()->create(['organization_id' => $organization->id, 'first_name' => 'Inactive', 'last_name' => 'One', 'status' => 'inactive']);
        GuardianProfile::query()->create(['organization_id' => $organization->id, 'first_name' => 'Archived', 'last_name' => 'One', 'status' => 'archived', 'archived_at' => now()]);
        $headers = ['X-Organization-Id' => $organization->id];
        $this->actingAs($admin, 'sanctum')->withHeaders($headers);

        $this->getJson('/api/v1/guardians?status=active')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/guardians?status=inactive')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/guardians?status=archived')->assertOk()->assertJsonCount(1, 'data');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/guardians')->assertOk();
        $membershipQueries = array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains($query['query'], 'organization_memberships'));
        $this->assertLessThanOrEqual(2, count($membershipQueries));
        DB::disableQueryLog();
    }

    public function test_identity_updates_are_idempotent_and_tenant_scoped(): void
    {
        [$organization, $admin] = $this->member('identity-a', 'Organization Administrator');
        [$other] = $this->member('identity-b', 'Organization Administrator');
        $identity = User::factory()->create();
        $membership = Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $identity->id, 'status' => 'active']);
        $guardian = GuardianProfile::query()->create(['organization_id' => $organization->id, 'user_id' => $identity->id, 'organization_membership_id' => $membership->id, 'first_name' => 'Identity', 'last_name' => 'One']);
        $service = app(GuardianService::class);
        $this->assertSame($membership->id, $service->update($guardian, $admin, ['organization_membership_id' => $membership->id])->organization_membership_id);
        $duplicate = GuardianProfile::query()->create(['organization_id' => $organization->id, 'first_name' => 'Identity', 'last_name' => 'Two']);
        $this->expectException(DomainException::class);
        $service->update($duplicate, $admin, ['organization_membership_id' => $membership->id]);
    }

    public function test_inactive_guardian_cannot_receive_active_relationship(): void
    {
        [$organization, $actor] = $this->member('inactive-link', 'Organization Administrator');
        $learner = LearnerProfile::factory()->create(['organization_id' => $organization->id]);
        $guardian = GuardianProfile::query()->create(['organization_id' => $organization->id, 'first_name' => 'Inactive', 'last_name' => 'Guardian', 'status' => 'inactive']);

        $this->expectException(DomainException::class);
        app(GuardianService::class)->link($learner, $guardian, $actor, ['relationship_type' => 'parent']);
    }

    public function test_capacity_handles_archival_restoration_and_organization_boundaries(): void
    {
        [$organization, $actor] = $this->member('capacity-full', 'Organization Administrator');
        [$other, $otherActor] = $this->member('capacity-other', 'Organization Administrator');
        License::query()->create(['license_key' => 'CAPACITY-FULL', 'licensee_type' => Organization::class, 'licensee_id' => $organization->id, 'tier' => LicenseTier::Starter, 'status' => LicenseStatus::Active, 'max_learners' => 1]);
        License::query()->create(['license_key' => 'CAPACITY-OTHER', 'licensee_type' => Organization::class, 'licensee_id' => $other->id, 'tier' => LicenseTier::Starter, 'status' => LicenseStatus::Active, 'max_learners' => 1]);
        $service = app(LearnerService::class);
        $first = $service->create($organization, $actor, ['first_name' => 'First', 'last_name' => 'Learner'], false);
        $otherLearner = $service->create($other, $otherActor, ['first_name' => 'Other', 'last_name' => 'Learner'], false);
        $this->assertNotNull($otherLearner->id);
        $archived = $service->archive($first, $actor, 'Capacity release');
        $replacement = $service->create($organization, $actor, ['first_name' => 'Replacement', 'last_name' => 'Learner'], false);
        try {
            $service->restore($archived, $actor, 'Should be full');
            $this->fail('Restoration at capacity should fail.');
        } catch (DomainException) {
            $this->assertSame('archived', $archived->refresh()->learner_status->value);
        }
        $service->archive($replacement, $actor, 'Release slot');
        $restored = $service->restore($archived->refresh(), $actor, 'Slot available');
        $this->assertNull($restored->archived_at);
        try {
            $service->create($organization, $actor, ['first_name' => 'Over', 'last_name' => 'Limit'], false);
            $this->fail('Restored learner must consume capacity.');
        } catch (DomainException) {
            $this->assertDatabaseCount('learner_profiles', 3);
        }
    }

    public function test_api_cross_organization_identifiers_are_non_enumerating(): void
    {
        [$organization, $admin] = $this->member('tenant-a', 'Organization Administrator');
        [$other, $otherAdmin] = $this->member('tenant-b', 'Organization Administrator');
        $learner = LearnerProfile::factory()->active()->create(['organization_id' => $organization->id]);
        $foreignLearner = LearnerProfile::factory()->active()->create(['organization_id' => $other->id]);
        $foreignGuardian = GuardianProfile::query()->create(['organization_id' => $other->id, 'first_name' => 'Foreign', 'last_name' => 'Guardian']);
        $foreignRelationship = app(GuardianService::class)->link($foreignLearner, $foreignGuardian, $otherAdmin, ['relationship_type' => 'parent']);
        $foreignIdentity = User::factory()->create();
        $foreignMembership = Membership::query()->create(['organization_id' => $other->id, 'user_id' => $foreignIdentity->id, 'status' => 'active']);
        $unsupportedIdentity = User::factory()->create();
        $unsupportedMembership = Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $unsupportedIdentity->id, 'status' => 'suspended']);
        $headers = ['X-Organization-Id' => $organization->id];
        $this->actingAs($admin, 'sanctum')->withHeaders($headers);

        $this->getJson("/api/v1/guardians/{$foreignGuardian->uuid}")->assertNotFound();
        $this->postJson("/api/v1/learners/{$learner->uuid}/guardians", ['guardian_uuid' => $foreignGuardian->uuid, 'relationship_type' => 'parent'])->assertNotFound();
        $this->patchJson("/api/v1/learners/{$learner->uuid}/guardians/{$foreignRelationship->uuid}", ['is_primary' => true])->assertNotFound();
        $this->deleteJson("/api/v1/learners/{$learner->uuid}/guardians/{$foreignRelationship->uuid}")->assertNotFound();
        $this->postJson("/api/v1/learners/{$learner->uuid}/guardians/consents", ['guardian_uuid' => $foreignGuardian->uuid, 'consent_type' => 'media', 'status' => 'granted', 'recorded_date' => today()->toDateString()])->assertNotFound();
        $this->postJson('/api/v1/guardians', ['first_name' => 'Forged', 'last_name' => 'Identity', 'preferred_communication_channel' => 'email', 'organization_membership_id' => $foreignMembership->id])->assertNotFound();
        $this->postJson('/api/v1/guardians', ['first_name' => 'Unsupported', 'last_name' => 'Identity', 'preferred_communication_channel' => 'email', 'organization_membership_id' => $unsupportedMembership->id])->assertNotFound();
    }

    public function test_guardian_serialization_hides_internal_and_unrelated_private_data(): void
    {
        [$organization, $admin] = $this->member('serialization', 'Organization Administrator');
        $viewer = User::factory()->create();
        $role = Role::query()->create(['name' => 'Guardian Viewer', 'is_system' => false]);
        $role->permissions()->attach(Permission::query()->where('name', 'guardians.view')->firstOrFail());
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $viewer->id, 'role_id' => $role->id, 'status' => 'active']);
        $identity = User::factory()->create();
        $membership = Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $identity->id, 'status' => 'active']);
        $guardian = GuardianProfile::query()->create(['organization_id' => $organization->id, 'user_id' => $identity->id, 'organization_membership_id' => $membership->id, 'first_name' => 'Private', 'last_name' => 'Guardian', 'address' => 'Private address']);
        $learner = LearnerProfile::factory()->active()->create(['organization_id' => $organization->id]);
        app(GuardianService::class)->link($learner, $guardian, $admin, ['relationship_type' => 'parent']);
        LearnerConsent::query()->create(['organization_id' => $organization->id, 'learner_profile_id' => $learner->id, 'guardian_profile_id' => $guardian->id, 'consent_type' => 'media', 'status' => 'granted', 'recorded_date' => today(), 'notes' => 'Never serialize']);

        $response = $this->actingAs($viewer, 'sanctum')->withHeader('X-Organization-Id', $organization->id)->getJson('/api/v1/guardians')->assertOk();
        $this->getJson('/api/v1/guardians?status=archived')->assertForbidden();
        $serialized = json_encode($response->json(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('identity_linked', $serialized);
        $this->assertStringNotContainsString('invitation_state', $serialized);
        $this->assertStringNotContainsString('organization_membership_id', $serialized);
        $this->assertStringNotContainsString('user_id', $serialized);
        $this->assertStringNotContainsString('created_by', $serialized);
        $this->assertStringNotContainsString('updated_by', $serialized);
        $this->assertStringNotContainsString('Private address', $serialized);
        $this->assertStringNotContainsString('Never serialize', $serialized);
    }

    public function test_guardian_resource_identity_fields_are_management_only(): void
    {
        [$organization, $admin] = $this->member('guardian-resource-privacy', 'Organization Administrator');
        $guardianUser = User::factory()->create();
        $membership = Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $guardianUser->id, 'status' => 'active']);
        $guardian = GuardianProfile::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $guardianUser->id,
            'organization_membership_id' => $membership->id,
            'first_name' => 'Resource',
            'last_name' => 'Guardian',
            'address' => 'Restricted address',
        ]);
        $headers = ['X-Organization-Id' => $organization->id];
        $url = "/api/v1/guardians/{$guardian->uuid}";

        $this->actingAs($admin, 'sanctum')->withHeaders($headers)->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.identity_linked', true)
            ->assertJsonPath('data.invitation_state', 'active')
            ->assertJsonPath('data.address', 'Restricted address');

        $response = $this->actingAs($guardianUser, 'sanctum')->withHeaders($headers)->getJson($url)
            ->assertOk()
            ->assertJsonMissing(['identity_linked'])
            ->assertJsonMissing(['invitation_state'])
            ->assertJsonMissing(['address']);
        $serialized = json_encode($response->json(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('user_id', $serialized);
        $this->assertStringNotContainsString('organization_membership_id', $serialized);
    }

    private function member(string $code, string $roleName): array
    {
        $this->seed(LearnersPermissionSeeder::class);
        $organization = Organization::query()->create(['name' => $code, 'code' => $code, 'type' => 'school']);
        OrganizationModule::query()->create(['organization_id' => $organization->id, 'module_name' => 'learners', 'enabled' => true]);
        $user = User::factory()->create();
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
        $this->actingAs($user);

        return [$organization, $user];
    }
}
