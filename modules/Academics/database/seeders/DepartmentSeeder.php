<?php

declare(strict_types=1);

namespace Modules\Academics\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Academics\Infrastructure\Models\Department;

/**
 * Example departments per modules/Academics/README.md#departments —
 * Science, Mathematics, Languages, Commerce, Humanities, Technology,
 * Arts. Organisations add Custom Departments through the API; these
 * are starting points, not a fixed list. Idempotent.
 */
final class DepartmentSeeder extends Seeder
{
    private const DEPARTMENTS = [
        ['code' => 'SCI', 'name' => 'Science'],
        ['code' => 'MATH', 'name' => 'Mathematics'],
        ['code' => 'LANG', 'name' => 'Languages'],
        ['code' => 'COMM', 'name' => 'Commerce'],
        ['code' => 'HUM', 'name' => 'Humanities'],
        ['code' => 'TECH', 'name' => 'Technology'],
        ['code' => 'ARTS', 'name' => 'Arts'],
    ];

    public function run(): void
    {
        foreach (self::DEPARTMENTS as $department) {
            Department::query()->firstOrCreate(['code' => $department['code']], $department);
        }
    }
}
