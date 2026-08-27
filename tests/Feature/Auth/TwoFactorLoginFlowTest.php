<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Core\Auth\Application\Totp;
use Core\Auth\Application\TwoFactorAuthenticationService;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Infrastructure\Models\OrganizationSetting;
use Tests\TestCase;

final class TwoFactorLoginFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_unaffected_when_two_factor_is_neither_enabled_nor_enforced(): void
    {
        User::factory()->create(['email' => 'plain@example.test']);

        $this->postJson('/api/v1/auth/login', ['email' => 'plain@example.test', 'password' => 'password'])
            ->assertOk()->assertJsonStructure(['data' => ['user', 'token', 'token_type']]);
    }

    public function test_api_login_with_two_factor_enabled_requires_the_challenge_step(): void
    {
        $user = User::factory()->create(['email' => 'has-2fa@example.test']);
        $secret = $this->enroll($user);

        $login = $this->postJson('/api/v1/auth/login', ['email' => 'has-2fa@example.test', 'password' => 'password'])
            ->assertOk();
        $login->assertJsonPath('data.two_factor', 'challenge_required');
        $this->assertArrayNotHasKey('token', $login->json('data'));
        $challengeToken = $login->json('data.two_factor_token');

        // Wrong code: no token issued.
        $this->postJson('/api/v1/auth/two-factor-challenge', ['two_factor_token' => $challengeToken, 'code' => '000000'])
            ->assertUnprocessable();

        // Correct code: real token issued.
        $this->postJson('/api/v1/auth/two-factor-challenge', [
            'two_factor_token' => $challengeToken,
            'code' => Totp::generate($secret),
        ])->assertOk()->assertJsonStructure(['data' => ['user', 'token', 'token_type']]);
    }

    public function test_api_challenge_accepts_a_recovery_code_and_consumes_it(): void
    {
        $user = User::factory()->create(['email' => 'recovery@example.test']);
        $this->enroll($user);
        $codes = $user->fresh()->getAttribute('two_factor_recovery_codes');

        $login = $this->postJson('/api/v1/auth/login', ['email' => 'recovery@example.test', 'password' => 'password'])->assertOk();
        $challengeToken = $login->json('data.two_factor_token');

        $this->postJson('/api/v1/auth/two-factor-challenge', ['two_factor_token' => $challengeToken, 'recovery_code' => $codes[0]])
            ->assertOk()->assertJsonStructure(['data' => ['token']]);

        // The same recovery code cannot be reused on a second login.
        $secondLogin = $this->postJson('/api/v1/auth/login', ['email' => 'recovery@example.test', 'password' => 'password'])->assertOk();
        $this->postJson('/api/v1/auth/two-factor-challenge', [
            'two_factor_token' => $secondLogin->json('data.two_factor_token'),
            'recovery_code' => $codes[0],
        ])->assertUnprocessable();
    }

    public function test_web_login_with_two_factor_enabled_redirects_to_the_challenge_then_completes(): void
    {
        $user = User::factory()->create(['email' => 'web-2fa@example.test']);
        $secret = $this->enroll($user);

        $this->post('/login', ['email' => 'web-2fa@example.test', 'password' => 'password'])
            ->assertRedirect(route('two-factor.challenge.create'));
        $this->assertGuest();

        $this->post(route('two-factor.challenge.store'), ['code' => Totp::generate($secret)]);
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_organization_enforced_two_factor_blocks_login_until_enrolled(): void
    {
        $user = User::factory()->create(['email' => 'enforced@example.test']);
        $organization = Organization::query()->create(['name' => 'enforced-org', 'code' => 'enforced-org', 'type' => 'school']);
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'status' => 'active']);
        OrganizationSetting::query()->create(['organization_id' => $organization->id, 'group' => 'security', 'key' => 'enforce_two_factor', 'value' => true]);

        $login = $this->postJson('/api/v1/auth/login', ['email' => 'enforced@example.test', 'password' => 'password'])->assertOk();
        $login->assertJsonPath('data.two_factor', 'setup_required');
        $this->assertArrayNotHasKey('token', $login->json('data'));

        // Web side: redirected to forced setup, not the challenge page (no
        // secret enrolled yet, so there is nothing to challenge).
        $this->post('/login', ['email' => 'enforced@example.test', 'password' => 'password'])
            ->assertRedirect(route('two-factor.setup.create'));
        $this->assertGuest();
    }

    public function test_completing_forced_setup_finishes_the_login(): void
    {
        $user = User::factory()->create(['email' => 'forced-setup@example.test']);
        $organization = Organization::query()->create(['name' => 'forced-org', 'code' => 'forced-org', 'type' => 'school']);
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'status' => 'active']);
        OrganizationSetting::query()->create(['organization_id' => $organization->id, 'group' => 'security', 'key' => 'enforce_two_factor', 'value' => true]);

        $this->post('/login', ['email' => 'forced-setup@example.test', 'password' => 'password']);
        $this->assertGuest();

        $enrollPage = $this->get(route('two-factor.setup.create'))->assertOk();
        $secret = $user->fresh()->getAttribute('two_factor_secret');
        $this->assertNotNull($secret, 'A pending secret should have been generated when visiting the forced setup page.');

        $this->post(route('two-factor.enroll.confirm'), ['code' => Totp::generate($secret)])
            ->assertRedirect(route('two-factor.recovery-codes'));
        $this->assertAuthenticatedAs($user->fresh());

        $recoveryPage = $this->get(route('two-factor.recovery-codes'))->assertOk();
        $recoveryPage->assertSee('recovery code', false); // sanity: not an error/redirect page
    }

    private function enroll(User $user): string
    {
        // Deliberately not actingAs() here: this helper must leave the test
        // client unauthenticated so the login-flow assertions that follow
        // (assertGuest(), posting to /login) reflect a real "not logged in
        // yet" starting state, not a leftover acting-as session.
        $service = app(TwoFactorAuthenticationService::class);
        $secret = $service->generateSecret($user);
        $service->confirm($user, Totp::generate($secret));

        return $secret;
    }
}
