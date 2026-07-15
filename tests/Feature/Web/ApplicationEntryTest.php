<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use Core\Identity\Domain\Enums\MembershipStatus;
use Core\Identity\Infrastructure\Models\Membership;
use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Domain\Enums\UserStatus;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Organizations\Domain\Enums\OrganizationStatus;
use Modules\Organizations\Infrastructure\Models\Organization;
use Tests\TestCase;

final class ApplicationEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_entry_and_login_pages_render_sky_fundi_content(): void
    {
        $this->get('/')->assertOk()->assertSee('Sky Fundi')->assertSee(route('login'));
        $this->get('/login')->assertOk()->assertSee('Welcome back')->assertSee('Email address');

        $this->assertStringNotContainsString('welcome', file_get_contents(base_path('routes/web.php')));
    }

    public function test_authenticated_user_is_redirected_away_from_public_entry_and_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertRedirect(route('access'));
        $this->actingAs($user)->get('/login')->assertRedirect('/dashboard');
        $this->actingAs($user)->get('/access')->assertOk()->assertSee('Access unavailable');
    }

    public function test_login_validates_input_and_uses_a_generic_failure(): void
    {
        User::factory()->create(['email' => 'known@example.test']);

        $this->post('/login', [])->assertSessionHasErrors(['email', 'password']);
        $known = $this->from('/login')->post('/login', ['email' => 'known@example.test', 'password' => 'wrong']);
        $unknown = $this->from('/login')->post('/login', ['email' => 'missing@example.test', 'password' => 'wrong']);

        $known->assertSessionHasErrors(['email' => 'These credentials could not be accepted.']);
        $unknown->assertSessionHasErrors(['email' => 'These credentials could not be accepted.']);
    }

    public function test_valid_credentials_create_a_regenerated_web_session(): void
    {
        $user = User::factory()->create(['email' => 'member@example.test']);
        $oldSessionId = session()->getId();

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertRedirect(route('access'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSessionId, session()->getId());
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_inactive_and_locked_accounts_are_denied_with_the_same_generic_message(): void
    {
        foreach ([UserStatus::Suspended, UserStatus::Deactivated, UserStatus::Locked] as $status) {
            $user = User::factory()->create([
                'email' => $status->value.'@example.test',
                'status' => $status,
                'locked_at' => $status === UserStatus::Locked ? now() : null,
            ]);

            $this->post('/login', ['email' => $user->email, 'password' => 'password'])
                ->assertSessionHasErrors(['email' => 'These credentials could not be accepted.']);
            $this->assertGuest();
        }
    }

    public function test_repeated_failed_web_logins_are_throttled_and_lock_the_account(): void
    {
        config(['services.auth.max_login_attempts' => 3]);
        $user = User::factory()->create(['email' => 'lock@example.test']);

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-'.$attempt]);
        }

        $this->assertSame(UserStatus::Locked, $user->fresh()->status);
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-6'])->assertStatus(429);
    }

    public function test_super_admin_login_redirects_to_the_protected_dashboard(): void
    {
        $permission = Permission::query()->create(['name' => 'core.roles.manage', 'description' => 'Manage roles', 'module' => 'core']);
        $role = Role::query()->create(['name' => 'Super Admin', 'description' => 'Platform owner', 'is_system' => true]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['email' => 'super@example.test']);
        $user->roles()->attach($role);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('super-admin.dashboard'));
        $this->get(route('super-admin.dashboard'))->assertOk();
    }

    public function test_non_super_admin_cannot_access_super_admin_routes(): void
    {
        $this->get(route('super-admin.dashboard'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('super-admin.dashboard'))->assertForbidden();
    }

    public function test_single_active_membership_establishes_trusted_context(): void
    {
        $user = User::factory()->create(['email' => 'one@example.test']);
        $membership = $this->membership($user);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('organization_id', $membership->organization_id);
        $this->get(route('dashboard'))->assertOk()->assertSee($membership->organization->name);
    }

    public function test_multiple_memberships_require_selection_and_reject_forged_organizations(): void
    {
        $user = User::factory()->create();
        $first = $this->membership($user);
        $second = $this->membership($user);

        $this->actingAs($user)->get(route('access'))->assertOk()
            ->assertSee($first->organization->name)->assertSee($second->organization->name);
        $this->post(route('access.organization'), ['organization_id' => (string) Str::uuid()])->assertNotFound();
        $this->post(route('access.organization'), ['organization_id' => $second->organization_id])
            ->assertRedirect(route('dashboard'))->assertSessionHas('organization_id', $second->organization_id);
    }

    public function test_inactive_memberships_and_suspended_organizations_are_not_usable(): void
    {
        $user = User::factory()->create();
        $inactive = $this->membership($user, MembershipStatus::Suspended);
        $suspended = $this->membership($user, MembershipStatus::Active, OrganizationStatus::Suspended);

        $this->actingAs($user)->get(route('access'))->assertOk()->assertSee('Access unavailable')
            ->assertDontSee($inactive->organization->name)->assertDontSee($suspended->organization->name);
    }

    public function test_organization_branding_is_used_only_after_trusted_selection(): void
    {
        $user = User::factory()->create();
        $membership = $this->membership($user);
        $membership->organization->settings()->create(['group' => 'branding', 'key' => 'platform_name', 'value' => 'Trusted Academy']);

        $this->actingAs($user)->withSession(['organization_id' => $membership->organization_id])
            ->get(route('dashboard'))->assertOk()->assertSee('Trusted Academy');
    }

    public function test_logout_invalidates_access_and_regenerates_the_csrf_token(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $oldToken = csrf_token();

        $this->post(route('logout'))->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNotSame($oldToken, csrf_token());
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_csrf_is_active_and_login_does_not_render_secrets_or_allow_open_redirects(): void
    {
        $user = User::factory()->create(['email' => 'safe@example.test', 'password' => Hash::make('very-secret-password')]);

        $middleware = app('router')->getRoutes()->getByName('login.store')?->gatherMiddleware() ?? [];
        $this->assertContains('web', $middleware);
        $this->get('/login')->assertDontSee('very-secret-password');
        $this->post('/login', ['email' => $user->email, 'password' => 'very-secret-password', 'redirect' => 'https://evil.test'])
            ->assertRedirect(route('access'));
    }

    private function membership(User $user, MembershipStatus $status = MembershipStatus::Active, OrganizationStatus $organizationStatus = OrganizationStatus::Active): Membership
    {
        $organization = Organization::query()->create([
            'name' => fake()->unique()->company(),
            'code' => fake()->unique()->bothify('ORG-####'),
            'type' => 'school',
            'status' => $organizationStatus,
        ]);
        $role = Role::query()->create(['name' => fake()->unique()->jobTitle(), 'description' => 'Organization role', 'is_system' => false]);

        return Membership::query()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'role_id' => $role->id,
            'status' => $status,
            'joined_at' => now(),
        ])->load(['organization.settings', 'role.permissions']);
    }
}
