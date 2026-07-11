<?php

declare(strict_types=1);

namespace Tests\Unit\RBAC;

use Core\RBAC\Application\PermissionService;
use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_has_a_permission_granted_via_a_role(): void
    {
        $permission = Permission::create(['name' => 'core.users.view', 'module' => 'core']);
        $role = Role::create(['name' => 'Viewer', 'is_system' => false]);
        $role->permissions()->attach($permission);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $service = $this->app->make(PermissionService::class);

        $this->assertTrue($service->check($user, 'core.users.view'));
        $this->assertFalse($service->check($user, 'core.users.manage'));
    }

    public function test_a_user_has_a_directly_granted_permission_without_any_role(): void
    {
        $permission = Permission::create(['name' => 'core.logs.view', 'module' => 'core']);
        $user = User::factory()->create();
        $user->directPermissions()->attach($permission);

        $service = $this->app->make(PermissionService::class);

        $this->assertTrue($service->check($user, 'core.logs.view'));
    }

    public function test_the_permission_cache_is_invalidated_after_forgetCacheFor(): void
    {
        $permission = Permission::create(['name' => 'core.users.view', 'module' => 'core']);
        $role = Role::create(['name' => 'Viewer', 'is_system' => false]);

        $user = User::factory()->create();
        $service = $this->app->make(PermissionService::class);

        $this->assertFalse($service->check($user, 'core.users.view'));

        $role->permissions()->attach($permission);
        $user->roles()->attach($role->id);
        $service->forgetCacheFor($user);

        $this->assertTrue($service->check($user, 'core.users.view'));
    }
}
