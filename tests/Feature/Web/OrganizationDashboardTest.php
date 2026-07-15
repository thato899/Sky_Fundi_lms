<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use Core\AuditLogs\Infrastructure\Models\AuditLog;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Licensing\Infrastructure\Models\License;
use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Subscriptions\Infrastructure\Models\Subscription;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\Curriculum;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Staff\Infrastructure\Models\StaffProfile;
use Tests\TestCase;

final class OrganizationDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_administrators_see_real_scoped_metrics_and_branding(): void
    {
        [$user, $organization, $membership] = $this->access('Organization Administrator', true);
        $other = $this->organization('Other Academy');
        LearnerProfile::factory()->active()->create(['organization_id' => $organization->id, 'portal_access_enabled' => true]);
        LearnerProfile::factory()->suspended()->create(['organization_id' => $organization->id]);
        LearnerProfile::factory()->active()->create(['organization_id' => $other->id]);
        StaffProfile::query()->create(['organization_id' => $organization->id, 'organization_membership_id' => $membership->id, 'user_id' => $user->id, 'employee_number' => 'STAFF-1', 'staff_type' => 'teacher', 'employment_status' => 'active']);
        AcademicYear::query()->create(['organization_id' => $organization->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'current', 'is_current' => true]);
        Curriculum::query()->create(['organization_id' => $organization->id, 'name' => 'National', 'code' => 'NAT', 'is_active' => true]);
        $organization->settings()->create(['group' => 'branding', 'key' => 'platform_name', 'value' => 'Trusted Academy']);

        $response = $this->actingAs($user)->withSession(['organization_id' => $membership->organization_id])->get(route('dashboard'));

        $response->assertOk()->assertSee('Trusted Academy')->assertSee('2026')->assertSee('Total learners')->assertSee('>2<', false)
            ->assertSee('Total staff')->assertDontSee('Other Academy')->assertSee('Log out')->assertSee('Not available yet');
    }

    public function test_dashboard_requires_permission_active_membership_and_active_organization(): void
    {
        [$teacher, $organization, $membership] = $this->access('Teacher', false);
        $this->actingAs($teacher)->withSession(['organization_id' => $organization->id])->get(route('dashboard'))->assertForbidden();

        $membership->update(['status' => 'suspended']);
        $this->actingAs($teacher)->withSession(['organization_id' => $organization->id])->get(route('dashboard'))->assertForbidden();

        $membership->update(['status' => 'active']);
        $organization->update(['status' => 'suspended']);
        $this->actingAs($teacher)->withSession(['organization_id' => $organization->id])->get(route('dashboard'))->assertForbidden();
    }

    public function test_forged_request_values_cannot_change_selected_organization(): void
    {
        [$user, $organization, $membership] = $this->access('Academic Administrator', true);
        $other = $this->organization('Forged School');
        LearnerProfile::factory()->count(4)->active()->create(['organization_id' => $other->id]);

        $this->actingAs($user)->withSession(['organization_id' => $membership->organization_id])
            ->get(route('dashboard', ['organization_id' => $other->id]))
            ->assertOk()->assertSee($organization->name)->assertDontSee('Forged School')->assertSee('>0<', false);
    }

    public function test_license_capacity_setup_gaps_and_activity_are_factual_and_scoped(): void
    {
        [$user, $organization, $membership] = $this->access('Organization Administrator', true);
        $other = $this->organization('Private Other');
        License::query()->create(['licensee_type' => Organization::class, 'licensee_id' => $organization->id, 'license_key' => 'LIC-ONE', 'tier' => 'professional', 'status' => 'active', 'max_users' => 5]);
        Subscription::query()->create(['subscriber_type' => Organization::class, 'subscriber_id' => $organization->id, 'plan' => 'School', 'billing_cycle' => 'monthly', 'status' => 'grace_period', 'started_at' => now(), 'current_users' => 1]);
        AuditLog::query()->create(['id' => (string) Str::uuid(), 'action' => 'organizations.updated', 'target_type' => Organization::class, 'target_id' => $organization->id, 'after' => ['secret' => 'never-render-this'], 'created_at' => now()]);
        AuditLog::query()->create(['id' => (string) Str::uuid(), 'action' => 'organizations.updated', 'target_type' => Organization::class, 'target_id' => $other->id, 'actor_email' => 'private@other.test', 'created_at' => now()]);

        $this->actingAs($user)->withSession(['organization_id' => $membership->organization_id])->get(route('dashboard'))
            ->assertOk()->assertSee('4 of 5 licensed user places remaining')->assertSee('grace period')
            ->assertSee('No learners have been added')
            ->assertSee('Organizations updated')->assertDontSee('never-render-this')->assertDontSee('private@other.test');
    }

    public function test_empty_activity_and_branding_fallback_render_without_dead_management_links(): void
    {
        [$user, $organization, $membership] = $this->access('Organization Administrator', true);

        $response = $this->actingAs($user)->withSession(['organization_id' => $membership->organization_id])->get(route('dashboard'));

        $response->assertOk()->assertSee($organization->name)->assertSee('No recent organization activity is available')
            ->assertSee('Sky Fundi')->assertDontSee('href="/learners"', false)->assertDontSee('href="/staff"', false);
    }

    /** @return array{User, Organization, Membership} */
    private function access(string $roleName, bool $allowed): array
    {
        $user = User::factory()->create();
        $organization = $this->organization(fake()->unique()->company());
        $role = Role::query()->create(['name' => $roleName, 'description' => $roleName, 'is_system' => false]);
        if ($allowed) {
            $permission = Permission::query()->firstOrCreate(['name' => 'organization.dashboard.view'], ['description' => 'View dashboard', 'module' => 'core']);
            $role->permissions()->attach($permission);
        }
        $membership = Membership::query()->create(['user_id' => $user->id, 'organization_id' => $organization->id, 'role_id' => $role->id, 'status' => 'active', 'joined_at' => now()]);

        return [$user, $organization, $membership];
    }

    private function organization(string $name): Organization
    {
        return Organization::query()->create(['name' => $name, 'code' => fake()->unique()->bothify('ORG-####'), 'type' => 'school', 'status' => 'active']);
    }
}
