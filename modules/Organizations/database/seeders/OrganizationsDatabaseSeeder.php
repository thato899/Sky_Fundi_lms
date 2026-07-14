<?php

declare(strict_types=1);

namespace Modules\Organizations\Database\Seeders;

use Illuminate\Database\Seeder;

final class OrganizationsDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(OrganizationsPermissionSeeder::class);
    }
}
