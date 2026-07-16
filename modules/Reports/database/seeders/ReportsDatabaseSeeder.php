<?php

declare(strict_types=1);

namespace Modules\Reports\Database\Seeders;

use Illuminate\Database\Seeder;

final class ReportsDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ReportsPermissionSeeder::class);
    }
}
