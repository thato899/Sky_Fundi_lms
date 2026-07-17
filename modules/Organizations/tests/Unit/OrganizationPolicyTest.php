<?php

declare(strict_types=1);

namespace Modules\Organizations\Tests\Unit;

use Core\RBAC\Infrastructure\Models\Permission;
use Core\Users\Infrastructure\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Organizations\Database\Seeders\OrganizationsPermissionSeeder;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Policies\OrganizationPolicy;
use Tests\TestCase;

final class OrganizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_administrator_is_scoped_to_the_assigned_organization(): void
    {
        $user = User::factory()->create();
        $own = $this->organization('own');
        $foreign = $this->organization('foreign');
        $own->administrators()->attach($user->id, ['assigned_at' => now()]);
        $policy = new OrganizationPolicy;

        $this->assertTrue($policy->view($user, $own));
        $this->assertTrue($policy->update($user, $own));
        $this->assertFalse($policy->delete($user, $own));
        $this->assertFalse($policy->view($user, $foreign));
        $this->assertFalse($policy->update($user, $foreign));
        $this->assertFalse($policy->delete($user, $foreign));
    }

    public function test_platform_permissions_are_the_explicit_global_control_plane(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(OrganizationsPermissionSeeder::class);
        $viewer = User::factory()->create();
        $manager = User::factory()->create();
        $viewer->directPermissions()->attach(Permission::query()->where('name', 'organizations.view')->sole()->id);
        $manager->directPermissions()->attach(Permission::query()->where('name', 'organizations.manage')->sole()->id);
        $organization = $this->organization('target');
        $policy = new OrganizationPolicy;

        $this->assertTrue($policy->view($viewer, $organization));
        $this->assertFalse($policy->update($viewer, $organization));
        $this->assertFalse($policy->delete($viewer, $organization));
        $this->assertFalse($policy->view($manager, $organization));
        $this->assertTrue($policy->update($manager, $organization));
        $this->assertTrue($policy->delete($manager, $organization));
    }

    private function organization(string $code): Organization
    {
        return Organization::query()->create([
            'name' => ucfirst($code).' Organization',
            'code' => $code,
            'type' => 'school',
            'status' => 'active',
        ]);
    }
}
