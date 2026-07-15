<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Assessments\Database\Seeders\AssessmentsPermissionSeeder;
use Modules\Attendance\Database\Seeders\AttendancePermissionSeeder;
use Modules\Staff\Database\Seeders\StaffPermissionSeeder;

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
            OrganizationDashboardPermissionSeeder::class,
            StaffPermissionSeeder::class,
            AttendancePermissionSeeder::class,
            AssessmentsPermissionSeeder::class,
            SettingsSeeder::class,
            BrandingSeeder::class,
            SuperAdminUserSeeder::class,
        ]);
    }
}
