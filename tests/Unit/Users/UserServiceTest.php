<?php

declare(strict_types=1);

namespace Tests\Unit\Users;

use Core\Users\Application\UserService;
use Core\Users\Domain\Enums\UserStatus;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_failed_logins_locks_the_account_at_the_configured_threshold(): void
    {
        config(['services.auth.max_login_attempts' => 3]);

        $user = User::factory()->create();
        $service = $this->app->make(UserService::class);

        $service->recordFailedLogin($user);
        $service->recordFailedLogin($user);
        $this->assertSame(UserStatus::Active, $user->fresh()->status);

        $service->recordFailedLogin($user);
        $this->assertSame(UserStatus::Locked, $user->fresh()->status);
        $this->assertNotNull($user->fresh()->locked_at);
    }

    public function test_a_successful_login_resets_the_failed_attempt_counter(): void
    {
        $user = User::factory()->create(['failed_login_attempts' => 4]);
        $service = $this->app->make(UserService::class);

        $service->recordSuccessfulLogin($user, '127.0.0.1');

        $fresh = $user->fresh();
        $this->assertSame(0, $fresh->failed_login_attempts);
        $this->assertSame('127.0.0.1', $fresh->last_login_ip);
        $this->assertNotNull($fresh->last_login_at);
    }

    public function test_unlocking_a_user_restores_active_status_and_clears_the_counter(): void
    {
        $user = User::factory()->locked()->create();
        $service = $this->app->make(UserService::class);

        $service->unlock($user);

        $fresh = $user->fresh();
        $this->assertSame(UserStatus::Active, $fresh->status);
        $this->assertSame(0, $fresh->failed_login_attempts);
        $this->assertNull($fresh->locked_at);
    }

    public function test_password_expiry_is_detected_after_the_configured_window(): void
    {
        config(['services.auth.password_expiry_days' => 90]);

        $user = User::factory()->create(['password_changed_at' => now()->subDays(91)]);
        $service = $this->app->make(UserService::class);

        $this->assertTrue($service->passwordHasExpired($user));
    }

    public function test_password_expiry_is_false_within_the_configured_window(): void
    {
        config(['services.auth.password_expiry_days' => 90]);

        $user = User::factory()->create(['password_changed_at' => now()->subDays(10)]);
        $service = $this->app->make(UserService::class);

        $this->assertFalse($service->passwordHasExpired($user));
    }
}
