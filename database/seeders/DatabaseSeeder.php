<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Core-only seeding, per docs/roadmap.md v1.0 scope — no educational
 * sample data (see Prompt 2: "No sample educational tables"). Order
 * matters: permissions before roles, roles before the Super Admin user.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            SettingsSeeder::class,
            BrandingSeeder::class,
            SuperAdminUserSeeder::class,
        ]);
    }
}
