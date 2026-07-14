<?php

declare(strict_types=1);

namespace Core\Installer\Application\Steps;

use Core\Installer\Contracts\InstallerStepInterface;
use Core\RBAC\Application\RoleService;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Application\DTOs\CreateUserData;
use Core\Users\Application\UserService;
use Core\Users\Infrastructure\Models\User;

/**
 * Deliberately reuses Core\Users\UserService::create() and
 * Core\RBAC\RoleService::assignRoleToUser() rather than inserting rows
 * directly — the installer is not exempt from Core's own validation,
 * auditing, and event-firing for user/role changes. Mirrors
 * database/seeders/SuperAdminUserSeeder.php's approach but as an
 * installer step rather than a seeder, for platforms installed
 * interactively instead of via `db:seed`.
 */
final class AdministratorStep implements InstallerStepInterface
{
    public function __construct(
        private readonly UserService $users,
        private readonly RoleService $roles,
    ) {}

    public function key(): string
    {
        return 'administrator';
    }

    public function label(): string
    {
        return 'Initial administrator account';
    }

    public function isComplete(): bool
    {
        return Role::query()->where('name', 'Super Admin')->whereHas('users')->exists();
    }

    public function run(array $input): array
    {
        $user = $this->users->create(new CreateUserData(
            name: $input['name'],
            email: $input['email'],
            password: $input['password'],
        ));

        $superAdmin = Role::query()->where('name', 'Super Admin')->firstOrFail();
        $this->roles->assignRoleToUser($user, $superAdmin);

        return ['user_id' => $user->id, 'email' => $user->email];
    }
}
