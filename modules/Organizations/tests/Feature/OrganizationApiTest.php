<?php

declare(strict_types=1);

namespace Modules\Organizations\Tests\Feature;

use Core\AuditLogs\Infrastructure\Models\AuditLog;
use Core\Modules\Domain\Enums\ModuleStatus;
use Core\Modules\Infrastructure\Models\ModuleRegistration;
use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Infrastructure\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Organizations\Database\Seeders\OrganizationsPermissionSeeder;
use Modules\Organizations\Domain\Enums\OrganizationStatus;
use Modules\Organizations\Events\OrganizationActivated;
use Modules\Organizations\Events\OrganizationAdministratorAssigned;
use Modules\Organizations\Events\OrganizationAIProviderChanged;
use Modules\Organizations\Events\OrganizationBrandingChanged;
use Modules\Organizations\Events\OrganizationCreated;
use Modules\Organizations\Events\OrganizationDeleted;
use Modules\Organizations\Events\OrganizationModuleDisabled;
use Modules\Organizations\Events\OrganizationModuleEnabled;
use Modules\Organizations\Events\OrganizationSettingsUpdated;
use Modules\Organizations\Events\OrganizationSuspended;
use Modules\Organizations\Events\OrganizationUpdated;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Infrastructure\Models\OrganizationAiConfiguration;
use Tests\TestCase;

final class OrganizationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(OrganizationsPermissionSeeder::class);
    }

    public function test_platform_management_covers_crud_lifecycle_and_events(): void
    {
        Event::fake();
        $admin = $this->userWithPermissions('organizations.view', 'organizations.manage');

        $created = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/organizations', [
            'name' => 'Example College',
            'code' => 'example-college',
            'type' => 'college',
            'email' => 'office@example.test',
        ])->assertCreated()
            ->assertJsonPath('data.code', 'example-college')
            ->assertJsonPath('data.status', OrganizationStatus::Active->value);

        $organization = Organization::query()->findOrFail($created->json('data.id'));
        $this->assertSame($admin->id, $organization->created_by);
        Event::assertDispatched(OrganizationCreated::class);

        $this->getJson("/api/v1/organizations/{$organization->id}")
            ->assertOk()
            ->assertJsonPath('data.contact.email', 'office@example.test');

        $this->putJson("/api/v1/organizations/{$organization->id}", ['name' => 'Renamed College'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed College');
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'organizations.updated',
            'target_id' => $organization->id,
        ]);
        Event::assertDispatched(OrganizationUpdated::class);

        $this->postJson("/api/v1/organizations/{$organization->id}/suspend")
            ->assertOk()
            ->assertJsonPath('data.status', OrganizationStatus::Suspended->value);
        $this->postJson("/api/v1/organizations/{$organization->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', OrganizationStatus::Active->value);
        Event::assertDispatched(OrganizationSuspended::class);
        Event::assertDispatched(OrganizationActivated::class);

        $this->deleteJson("/api/v1/organizations/{$organization->id}")->assertNoContent();
        $this->assertSoftDeleted('organizations', ['id' => $organization->id]);
        Event::assertDispatched(OrganizationDeleted::class);
    }

    public function test_directory_filters_and_scopes_non_managers_to_administered_organizations(): void
    {
        $manager = $this->userWithPermissions('organizations.view', 'organizations.manage');
        $assigned = $this->organization(['name' => 'Alpha School', 'code' => 'alpha', 'type' => 'school']);
        $this->organization(['name' => 'Beta College', 'code' => 'beta', 'type' => 'college', 'status' => OrganizationStatus::Suspended]);
        $viewer = $this->userWithPermissions('organizations.view');
        $assigned->administrators()->attach($viewer->id, ['assigned_by' => $manager->id, 'assigned_at' => now()]);

        $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/organizations?search=Alpha&type=school&status=active&sort=code&direction=desc&per_page=5')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assigned->id)
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($manager, 'sanctum')->getJson('/api/v1/organizations?type=college')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'beta');
    }

    public function test_directory_rejects_unsafe_sort_direction_and_page_size_without_querying(): void
    {
        $manager = $this->userWithPermissions('organizations.view', 'organizations.manage');
        $this->organization();

        foreach ([
            'sort' => 'license_key',
            'direction' => 'sideways',
            'per_page' => 101,
            'page' => 0,
        ] as $field => $value) {
            $this->actingAs($manager, 'sanctum')
                ->getJson('/api/v1/organizations?'.http_build_query([$field => $value]))
                ->assertUnprocessable()
                ->assertJsonPath('error.code', 'validation_failed')
                ->assertJsonValidationErrors($field);
        }

        $this->assertDatabaseCount('organizations', 1);
    }

    public function test_creation_and_update_validation_reject_invalid_or_duplicate_values(): void
    {
        $admin = $this->userWithPermissions('organizations.manage');
        $organization = $this->organization(['code' => 'existing']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/organizations', [
            'name' => '',
            'code' => 'not valid!',
            'type' => 'unsupported',
            'country' => 'ZAF',
            'timezone' => 'Mars/Olympus',
            'storage_quota' => -1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'code', 'type', 'country', 'timezone', 'storage_quota']);

        $other = $this->organization(['code' => 'other']);
        $this->putJson("/api/v1/organizations/{$other->id}", ['code' => $organization->code])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_settings_and_branding_are_scoped_and_branding_inherits_platform_defaults(): void
    {
        Event::fake();
        $actor = $this->userWithPermissions('organizations.settings.manage', 'organizations.branding.manage');
        $organization = $this->organization();
        $organization->administrators()->attach($actor->id, ['assigned_at' => now()]);

        $this->actingAs($actor, 'sanctum')->putJson("/api/v1/organizations/{$organization->id}/settings", [
            'settings' => ['general' => ['timezone' => 'Africa/Nairobi'], 'notifications' => ['digest' => true]],
        ])->assertOk()
            ->assertJsonPath('data.general.timezone', 'Africa/Nairobi')
            ->assertJsonPath('data.general.date_format', 'Y-m-d')
            ->assertJsonPath('data.notifications.digest', true);
        Event::assertDispatched(OrganizationSettingsUpdated::class);

        $this->putJson("/api/v1/organizations/{$organization->id}/branding", [
            'settings' => ['branding' => ['primary_color' => '#123456']],
        ])->assertOk()
            ->assertJsonPath('data.primary_color', '#123456')
            ->assertJsonPath('data.platform_name', 'Sky Fundi');
        Event::assertDispatched(OrganizationBrandingChanged::class);

        $this->getJson("/api/v1/organizations/{$organization->id}/settings")
            ->assertOk()
            ->assertJsonPath('data.branding.primary_color', '#123456');
    }

    public function test_administrator_assignment_is_idempotent_and_policy_scoped(): void
    {
        Event::fake();
        $platformAdmin = $this->userWithPermissions('organizations.users.manage', 'organizations.manage');
        $organization = $this->organization();
        $assignedAdmin = $this->userWithPermissions('organizations.view');

        $this->actingAs($platformAdmin, 'sanctum')->postJson("/api/v1/organizations/{$organization->id}/administrators", ['user_id' => $assignedAdmin->id])
            ->assertOk()
            ->assertJsonPath('data.administrators.0.id', $assignedAdmin->id);
        $this->postJson("/api/v1/organizations/{$organization->id}/administrators", ['user_id' => $assignedAdmin->id])->assertOk();
        $this->assertDatabaseCount('organization_administrators', 1);
        Event::assertDispatched(OrganizationAdministratorAssigned::class, 2);

        $this->actingAs($assignedAdmin, 'sanctum')->getJson("/api/v1/organizations/{$organization->id}")->assertOk();
        $this->deleteJson("/api/v1/organizations/{$organization->id}")->assertForbidden();
    }

    public function test_ai_configuration_is_encrypted_at_rest_and_credentials_are_never_returned(): void
    {
        Event::fake();
        $actor = $this->userWithPermissions('organizations.ai.manage');
        $organization = $this->organization();
        $organization->administrators()->attach($actor->id, ['assigned_at' => now()]);
        $credentials = ['api_key' => 'organization-secret-key'];

        $response = $this->actingAs($actor, 'sanctum')->putJson("/api/v1/organizations/{$organization->id}/ai", [
            'provider' => 'openai',
            'credentials' => $credentials,
            'configuration' => ['model' => 'approved-model'],
        ])->assertOk()
            ->assertJsonPath('data.provider', 'openai')
            ->assertJsonMissingPath('data.credentials');

        $this->assertStringNotContainsString('organization-secret-key', $response->getContent());
        $rawCredentials = DB::table('organization_ai_configurations')->where('organization_id', $organization->id)->value('credentials');
        $this->assertIsString($rawCredentials);
        $this->assertStringNotContainsString('organization-secret-key', $rawCredentials);
        $this->assertSame($credentials, OrganizationAiConfiguration::query()->where('organization_id', $organization->id)->sole()->credentials);
        Event::assertDispatched(OrganizationAIProviderChanged::class);
    }

    public function test_module_assignment_delegates_to_the_registry_and_records_both_states(): void
    {
        Event::fake();
        $actor = $this->userWithPermissions('organizations.modules.manage');
        $organization = $this->organization();
        $organization->administrators()->attach($actor->id, ['assigned_at' => now()]);
        ModuleRegistration::create([
            'name' => 'sample-module',
            'display_name' => 'Sample Module',
            'version' => '1.0.0',
            'status' => ModuleStatus::Installed,
        ]);

        $this->actingAs($actor, 'sanctum')->putJson("/api/v1/organizations/{$organization->id}/modules", [
            'module_name' => 'sample-module',
            'enabled' => true,
        ])->assertOk()
            ->assertJsonPath('data.module_name', 'sample-module')
            ->assertJsonPath('data.enabled', true);
        $this->assertContains($organization->id, ModuleRegistration::query()->where('name', 'sample-module')->sole()->enabled_for_tenants);
        Event::assertDispatched(OrganizationModuleEnabled::class);

        $this->putJson("/api/v1/organizations/{$organization->id}/modules", ['module_name' => 'sample-module', 'enabled' => false])
            ->assertOk()
            ->assertJsonPath('data.enabled', false);
        $this->assertSame([], ModuleRegistration::query()->where('name', 'sample-module')->sole()->enabled_for_tenants);
        Event::assertDispatched(OrganizationModuleDisabled::class);
    }

    public function test_authentication_permissions_policy_and_request_validation_deny_unauthorized_access(): void
    {
        $organization = $this->organization();

        $this->getJson('/api/v1/organizations')->assertUnauthorized();
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/v1/organizations')->assertForbidden();

        $specialist = $this->userWithPermissions('organizations.settings.manage');
        $this->actingAs($specialist, 'sanctum')->getJson("/api/v1/organizations/{$organization->id}/settings")->assertForbidden();

        $organization->administrators()->attach($specialist->id, ['assigned_at' => now()]);
        $this->getJson("/api/v1/organizations/{$organization->id}/settings")->assertOk();
        $this->putJson("/api/v1/organizations/{$organization->id}/settings", ['settings' => 'invalid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('settings');

        $this->postJson("/api/v1/organizations/{$organization->id}/administrators", ['user_id' => 'not-a-uuid'])->assertForbidden();
        $this->putJson("/api/v1/organizations/{$organization->id}/modules", ['module_name' => '', 'enabled' => 'yes'])->assertForbidden();
    }

    public function test_foreign_administrator_cannot_mutate_configuration_relationships_or_audit_history(): void
    {
        $actor = $this->userWithPermissions(
            'organizations.settings.manage',
            'organizations.branding.manage',
            'organizations.ai.manage',
            'organizations.modules.manage',
            'organizations.users.manage',
        );
        $own = $this->organization(['code' => 'own-organization']);
        $foreign = $this->organization(['code' => 'foreign-organization']);
        $own->administrators()->attach($actor->id, ['assigned_at' => now()]);
        $candidate = User::factory()->create();

        $this->actingAs($actor, 'sanctum')
            ->putJson("/api/v1/organizations/{$foreign->id}/settings", ['settings' => ['general' => ['timezone' => 'UTC']]])
            ->assertForbidden();
        $this->putJson("/api/v1/organizations/{$foreign->id}/branding", ['settings' => ['branding' => ['platform_name' => 'Forged']]])
            ->assertForbidden();
        $this->putJson("/api/v1/organizations/{$foreign->id}/ai", ['provider' => 'forged', 'credentials' => ['api_key' => 'fake-secret']])
            ->assertForbidden();
        $this->putJson("/api/v1/organizations/{$foreign->id}/modules", ['module_name' => 'forged', 'enabled' => true])
            ->assertForbidden();
        $this->postJson("/api/v1/organizations/{$foreign->id}/administrators", ['user_id' => $candidate->id])
            ->assertForbidden();

        $this->assertDatabaseMissing('organization_settings', ['organization_id' => $foreign->id]);
        $this->assertDatabaseMissing('organization_ai_configurations', ['organization_id' => $foreign->id]);
        $this->assertDatabaseMissing('organization_modules', ['organization_id' => $foreign->id]);
        $this->assertDatabaseMissing('organization_administrators', ['organization_id' => $foreign->id, 'user_id' => $candidate->id]);
        $this->assertDatabaseMissing('audit_logs', ['target_id' => $foreign->id]);
        $this->assertSame('foreign-organization', $foreign->fresh()->code);
    }

    public function test_ai_audit_owns_the_target_and_never_records_credentials(): void
    {
        $actor = $this->userWithPermissions('organizations.ai.manage');
        $organization = $this->organization();
        $organization->administrators()->attach($actor->id, ['assigned_at' => now()]);

        $this->actingAs($actor, 'sanctum')->putJson("/api/v1/organizations/{$organization->id}/ai", [
            'provider' => 'openai',
            'credentials' => ['api_key' => 'fake-audit-secret'],
            'configuration' => ['model' => 'approved-model'],
        ])->assertOk()
            ->assertJsonMissingPath('data.credentials');

        $audit = AuditLog::query()->where('action', 'organizations.ai_provider.changed')->sole();
        $this->assertSame($actor->id, $audit->actor_id);
        $this->assertSame($organization->id, $audit->target_id);
        $this->assertSame(['organization_id' => $organization->id], $audit->after);
        $this->assertStringNotContainsString('fake-audit-secret', $audit->toJson());
    }

    public function test_show_response_excludes_internal_and_sensitive_organization_state(): void
    {
        $admin = $this->userWithPermissions('organizations.view', 'organizations.manage');
        $organization = $this->organization([
            'license_key' => 'fake-license-secret',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        OrganizationAiConfiguration::query()->create([
            'organization_id' => $organization->id,
            'provider' => 'openai',
            'credentials' => ['api_key' => 'fake-provider-secret'],
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson("/api/v1/organizations/{$organization->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'name', 'code', 'type', 'status', 'contact', 'address', 'usage', 'license']])
            ->assertJsonMissingPath('data.license_key')
            ->assertJsonMissingPath('data.created_by')
            ->assertJsonMissingPath('data.updated_by')
            ->assertJsonMissingPath('data.ai_configuration');

        $this->assertStringNotContainsString('fake-license-secret', $response->getContent());
        $this->assertStringNotContainsString('fake-provider-secret', $response->getContent());
    }

    private function userWithPermissions(string ...$permissions): User
    {
        $role = Role::create(['name' => 'Test role '.uniqid(), 'is_system' => false]);
        $role->permissions()->attach(Permission::query()->whereIn('name', $permissions)->pluck('id'));
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function organization(array $attributes = []): Organization
    {
        return Organization::create(array_replace([
            'name' => 'Example Organization',
            'code' => 'organization-'.uniqid(),
            'type' => 'school',
            'status' => OrganizationStatus::Active,
        ], $attributes));
    }
}
