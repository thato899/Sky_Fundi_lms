<?php

declare(strict_types=1);

namespace Database\Factories;

use Core\Users\Domain\Enums\UserStatus;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'status' => UserStatus::Active,
            'timezone' => 'UTC',
            'locale' => 'en',
            'password_changed_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
            'status' => UserStatus::PendingVerification,
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Locked,
            'locked_at' => now(),
            'failed_login_attempts' => 5,
        ]);
    }
}
