<?php

declare(strict_types=1);

namespace Core\Billing\Database\Seeders;

use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Grants the existing `core.billing.manage` permission (declared in
 * config/permissions.php, seeded by the root PermissionSeeder) to the
 * per-organization "Organization Administrator" role — mirroring
 * every module's own PermissionSeeder (see e.g.
 * Modules\Learners\Database\Seeders\LearnersPermissionSeeder). Before
 * core/Billing, this Core permission was only granted to the
 * platform-wide "Platform Administrator" role by database/seeders/RoleSeeder.php,
 * so an ordinary org admin could not self-service billing at all —
 * this seeder is what makes "an org admin can subscribe to a real
 * plan" true. Idempotent.
 */
final class BillingPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permission = Permission::query()->where('name', 'core.billing.manage')->first();
        if ($permission === null) {
            // PermissionSeeder (config/permissions.php) has not run yet
            // in this context — nothing to grant.
            return;
        }

        $role = Role::query()->firstOrCreate(['name' => 'Organization Administrator'], ['is_system' => false]);
        $role->permissions()->syncWithoutDetaching([$permission->getKey()]);
    }
}
