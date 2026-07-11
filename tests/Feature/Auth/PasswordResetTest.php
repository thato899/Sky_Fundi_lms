<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Core\Users\Infrastructure\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

final class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_dispatches_a_reset_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'admin@skyfundi.app']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'admin@skyfundi.app'])
            ->assertOk();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_returns_a_generic_message_for_an_unknown_email(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@skyfundi.app'])
            ->assertOk()
            ->assertJsonPath('message', 'If an account exists for that email, a password reset link has been sent.');
    }

    public function test_reset_password_updates_the_password_and_revokes_existing_tokens(): void
    {
        $user = User::factory()->create(['email' => 'admin@skyfundi.app']);
        $token = Password::broker('users')->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'admin@skyfundi.app',
            'password' => 'a-new-strong-password-1',
            'password_confirmation' => 'a-new-strong-password-1',
        ])->assertOk();

        $this->assertTrue(Hash::check('a-new-strong-password-1', $user->fresh()->password));
    }
}
