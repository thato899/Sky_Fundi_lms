<?php

declare(strict_types=1);

namespace Tests\Feature\RBAC;

use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Infrastructure\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PermissionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_without_the_required_permission_is_forbidden(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/users')
            ->assertStatus(403);
    }

    public function test_a_user_with_the_required_permission_via_a_role_is_allowed(): void
    {
        $this->seed(PermissionSeeder::class);

        $role = Role::create(['name' => 'Test Viewer', 'is_system' => false]);
        $role->permissions()->attach(Permission::query()->where('name', 'core.users.view')->firstOrFail());

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/users')
            ->assertOk();
    }

    public function test_a_direct_permission_override_also_grants_access(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create();
        $user->directPermissions()->attach(Permission::query()->where('name', 'core.users.view')->firstOrFail());

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/users')
            ->assertOk();
    }

    public function test_revoking_a_role_revokes_derived_access(): void
    {
        $this->seed(PermissionSeeder::class);

        $role = Role::create(['name' => 'Test Viewer', 'is_system' => false]);
        $role->permissions()->attach(Permission::query()->where('name', 'core.users.view')->firstOrFail());

        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        $user->roles()->detach($role->id);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/users')
            ->assertStatus(403);
    }
}
