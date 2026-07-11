<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Core\Users\Domain\Enums\UserStatus;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_log_in_with_valid_credentials(): void
    {
        $user = User::factory()->create(['email' => 'admin@skyfundi.app']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@skyfundi.app',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.email', 'admin@skyfundi.app')
            ->assertJsonStructure(['data' => ['user', 'token', 'token_type']]);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        User::factory()->create(['email' => 'admin@skyfundi.app']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@skyfundi.app',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_account_locks_after_max_failed_attempts(): void
    {
        config(['services.auth.max_login_attempts' => 3]);

        $user = User::factory()->create(['email' => 'admin@skyfundi.app']);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'admin@skyfundi.app',
                'password' => 'wrong-password',
            ]);
        }

        $this->assertSame(UserStatus::Locked, $user->fresh()->status);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@skyfundi.app',
            'password' => 'password',
        ]);

        $response->assertStatus(403);
    }

    public function test_a_locked_account_cannot_use_a_previously_issued_token(): void
    {
        $user = User::factory()->locked()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/auth/logout');

        $response->assertStatus(423);
    }
}
