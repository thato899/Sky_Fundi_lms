<?php

declare(strict_types=1);

namespace Tests\Feature\Users;

use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Domain\Enums\UserStatus;
use Core\Users\Infrastructure\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $this->seed(PermissionSeeder::class);

        $role = Role::create(['name' => 'Test Admin', 'is_system' => false]);
        $role->permissions()->attach(Permission::query()->pluck('id'));

        $admin = User::factory()->create();
        $admin->roles()->attach($role->id);

        return $admin;
    }

    public function test_an_administrator_can_create_a_user(): void
    {
        $admin = $this->actingAdmin();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/users', [
            'name' => 'Jane Doe',
            'email' => 'jane@skyfundi.app',
            'password' => 'a-strong-password-1',
            'password_confirmation' => 'a-strong-password-1',
        ]);

        $response->assertCreated()->assertJsonPath('data.email', 'jane@skyfundi.app');

        $this->assertDatabaseHas('users', ['email' => 'jane@skyfundi.app']);
    }

    public function test_an_administrator_can_suspend_and_reactivate_a_user(): void
    {
        $admin = $this->actingAdmin();
        $target = User::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/users/{$target->id}/suspend", ['reason' => 'policy violation'])
            ->assertOk()
            ->assertJsonPath('data.status', UserStatus::Suspended->value);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/users/{$target->id}/reactivate")
            ->assertOk()
            ->assertJsonPath('data.status', UserStatus::Active->value);
    }

    public function test_creating_a_user_requires_a_unique_email(): void
    {
        $admin = $this->actingAdmin();
        User::factory()->create(['email' => 'taken@skyfundi.app']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/users', [
            'name' => 'Someone Else',
            'email' => 'taken@skyfundi.app',
            'password' => 'a-strong-password-1',
            'password_confirmation' => 'a-strong-password-1',
        ])->assertStatus(422);
    }
}
