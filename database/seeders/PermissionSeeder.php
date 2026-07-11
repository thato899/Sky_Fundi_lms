<?php

declare(strict_types=1);

namespace Database\Seeders;

use Core\RBAC\Infrastructure\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Seeds the Core permission catalog from config/permissions.php — the
 * single source of truth for Core-owned permissions (module
 * permissions are registered separately when a module is installed,
 * per docs/architecture/module-system.md#module-manifest-modulejson).
 * Idempotent — safe to re-run.
 */
final class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('permissions', []) as $name => $description) {
            Permission::query()->updateOrCreate(
                ['name' => $name],
                ['module' => 'core', 'description' => $description],
            );
        }
    }
}
