<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use Core\Billing\Database\Seeders\BillingPermissionSeeder;
use Core\Identity\Infrastructure\Models\Membership;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Subscriptions\Infrastructure\Models\Plan;
use Core\Users\Infrastructure\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Organizations\Infrastructure\Models\Organization;
use Tests\TestCase;

final class PlanCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_member_lists_only_active_plans_ordered_by_price(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(BillingPermissionSeeder::class);
        Plan::query()->create(['key' => 'school_pro', 'name' => 'School Pro', 'price' => 3999, 'currency' => 'ZAR', 'billing_cycle' => 'monthly', 'is_active' => true]);
        Plan::query()->create(['key' => 'starter', 'name' => 'Starter', 'price' => 499, 'currency' => 'ZAR', 'billing_cycle' => 'monthly', 'is_active' => true]);
        Plan::query()->create(['key' => 'retired', 'name' => 'Retired', 'price' => 1, 'currency' => 'ZAR', 'billing_cycle' => 'monthly', 'is_active' => false]);

        $organization = Organization::query()->create(['name' => 'plans-org', 'code' => 'plans-org', 'type' => 'school']);
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(['name' => 'Organization Administrator'], ['is_system' => false]);
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active', 'is_default' => true]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/plans')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'starter')
            ->assertJsonPath('data.1.key', 'school_pro')
            ->assertJsonCount(2, 'data');
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/plans')->assertUnauthorized();
    }
}
