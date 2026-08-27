<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Core\Auth\Application\Totp;
use Core\Auth\Application\TwoFactorAuthenticationService;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrollment_is_inert_until_confirmed(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $service = app(TwoFactorAuthenticationService::class);

        $secret = $service->generateSecret($user);
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
        $this->assertNotEmpty($service->provisioningUri($user));

        try {
            $service->confirm($user, '000000');
            $this->fail('An invalid confirmation code was accepted.');
        } catch (DomainException) {
            $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
        }

        $codes = $service->confirm($user, Totp::generate($secret));
        $confirmed = $user->fresh();
        $this->assertTrue($confirmed->hasTwoFactorEnabled());
        $this->assertCount(8, $codes);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.two_factor_enabled']);
    }

    public function test_recovery_codes_are_single_use(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $service = app(TwoFactorAuthenticationService::class);
        $secret = $service->generateSecret($user);
        $codes = $service->confirm($user, Totp::generate($secret));
        $user = $user->fresh();

        $this->assertTrue($service->verifyRecoveryCode($user, $codes[0]));
        $this->assertFalse($service->verifyRecoveryCode($user->fresh(), $codes[0]));
        $this->assertTrue($service->verifyRecoveryCode($user->fresh(), $codes[1]));
    }

    public function test_disable_clears_secret_and_recovery_codes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $service = app(TwoFactorAuthenticationService::class);
        $secret = $service->generateSecret($user);
        $service->confirm($user, Totp::generate($secret));

        $service->disable($user->fresh());

        $disabled = $user->fresh();
        $this->assertFalse($disabled->hasTwoFactorEnabled());
        $this->assertNull($disabled->getAttribute('two_factor_secret'));
        $this->assertNull($disabled->getAttribute('two_factor_recovery_codes'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.two_factor_disabled']);
    }

    public function test_regenerate_recovery_codes_requires_two_factor_to_already_be_enabled(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $service = app(TwoFactorAuthenticationService::class);

        $this->expectException(DomainException::class);
        $service->regenerateRecoveryCodes($user);
    }

    public function test_api_self_service_enroll_confirm_and_disable(): void
    {
        $user = User::factory()->create();

        $enroll = $this->actingAs($user, 'sanctum')->postJson('/api/v1/auth/two-factor')
            ->assertOk()->assertJsonStructure(['data' => ['secret', 'provisioning_uri']]);
        $secret = $enroll->json('data.secret');

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/auth/two-factor/confirm', ['code' => Totp::generate($secret)])
            ->assertOk()->assertJsonCount(8, 'data.recovery_codes');

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/auth/two-factor', ['password' => 'password'])
            ->assertNoContent();
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_api_disable_rejects_the_wrong_password(): void
    {
        $user = User::factory()->create();
        $service = app(TwoFactorAuthenticationService::class);
        $this->actingAs($user);
        $secret = $service->generateSecret($user);
        $service->confirm($user, Totp::generate($secret));

        $this->actingAs($user->fresh(), 'sanctum')->deleteJson('/api/v1/auth/two-factor', ['password' => 'wrong-password'])
            ->assertUnprocessable();
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }
}
