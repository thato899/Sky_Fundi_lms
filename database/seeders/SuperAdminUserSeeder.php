<?php

declare(strict_types=1);

namespace Database\Seeders;

use Core\RBAC\Application\RoleService;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Domain\Enums\UserStatus;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the platform's first Super Admin user from environment
 * variables, so a fresh install always has an initial administrator
 * without a plaintext credential ever being committed to the
 * repository. See docs/environment-variables.md and
 * docs/security/policies.md. Idempotent — does nothing if the account
 * already exists.
 */
final class SuperAdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPER_ADMIN_EMAIL');
        $password = env('SUPER_ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->command?->warn('SUPER_ADMIN_EMAIL / SUPER_ADMIN_PASSWORD not set — skipping Super Admin seeding.');

            return;
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Super Admin',
                'password' => Hash::make($password),
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
                'password_changed_at' => now(),
            ],
        );

        $superAdminRole = Role::query()->where('name', 'Super Admin')->firstOrFail();

        app(RoleService::class)->assignRoleToUser($user, $superAdminRole);
    }
}
