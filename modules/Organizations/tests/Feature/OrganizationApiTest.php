<?php

declare(strict_types=1);

namespace Modules\Organizations\Tests\Feature;

use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Infrastructure\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Organizations\Database\Seeders\OrganizationsPermissionSeeder;
use Tests\TestCase;

final class OrganizationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_platform_administrator_can_create_an_organization(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(OrganizationsPermissionSeeder::class);
        $role = Role::create(['name' => 'Platform Administrator', 'is_system' => false]);
        $role->permissions()->attach(Permission::query()->where('name', 'organizations.manage')->pluck('id'));
        $admin = User::factory()->create();
        $admin->roles()->attach($role->id);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/organizations', ['name' => 'Example College', 'code' => 'example-college', 'type' => 'college'])
            ->assertCreated()->assertJsonPath('data.code', 'example-college');
    }
}
