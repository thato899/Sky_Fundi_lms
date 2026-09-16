<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Core\Security\Infrastructure\Models\TwoFactorAuthentication;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

final class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_user_can_begin_mfa_enrollment(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/security/two-factor/begin');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['secret', 'otpauth_uri']]);

        $this->assertDatabaseHas('two_factor_authentication', ['user_id' => $user->id]);
    }

    public function test_enrollment_requires_a_valid_code_before_it_is_enabled(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/security/two-factor/begin')->assertOk();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/security/two-factor/confirm', ['code' => '000000'])
            ->assertUnprocessable();

        $this->assertDatabaseHas('two_factor_authentication', [
            'user_id' => $user->id,
            'confirmed_at' => null,
        ]);
    }

    public function test_api_login_requires_mfa_for_a_confirmed_account(): void
    {
        $user = User::factory()->create(['email' => 'mfa@example.test']);

        TwoFactorAuthentication::query()->create([
            'user_id' => $user->id,
            'secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
            'recovery_codes' => ['RECOVERY-1'],
            'confirmed_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'mfa@example.test',
            'password' => 'password',
        ])->assertUnprocessable()->assertJsonValidationErrors('two_factor_code');
    }
}
