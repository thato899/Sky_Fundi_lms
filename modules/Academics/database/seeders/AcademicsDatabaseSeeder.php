<?php

declare(strict_types=1);

namespace Modules\Academics\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Runs every Academics seeder in order. Invoke explicitly — not
 * called by Core\Database\Seeders\DatabaseSeeder, which is
 * deliberately Core-only:
 *
 *   php artisan db:seed --class="Modules\Academics\Database\Seeders\AcademicsDatabaseSeeder"
 */
final class AcademicsDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AcademicsPermissionSeeder::class,
            CurriculumSeeder::class,
            DepartmentSeeder::class,
        ]);
    }
}
