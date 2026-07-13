<?php

declare(strict_types=1);

namespace Modules\Academics\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Academics\Infrastructure\Models\Curriculum;

/**
 * Example curricula — CAPS, IEB, Cambridge, and a generic Custom
 * entry — per modules/Academics/README.md#curriculum ("Curriculum
 * should not be hardcoded"). Organisations add their own rows through
 * the API; these are starting points, not a fixed list. Idempotent.
 */
final class CurriculumSeeder extends Seeder
{
    private const CURRICULA = [
        ['code' => 'CAPS', 'name' => 'Curriculum and Assessment Policy Statement', 'description' => 'South African national curriculum.'],
        ['code' => 'IEB', 'name' => 'Independent Examinations Board', 'description' => 'South African independent schools curriculum and assessment body.'],
        ['code' => 'CAMBRIDGE', 'name' => 'Cambridge International', 'description' => 'Cambridge Assessment International Education curriculum.'],
        ['code' => 'CUSTOM', 'name' => 'Custom Curriculum', 'description' => 'A starting point for an organisation-defined curriculum.'],
    ];

    public function run(): void
    {
        foreach (self::CURRICULA as $curriculum) {
            Curriculum::query()->firstOrCreate(['code' => $curriculum['code']], $curriculum);
        }
    }
}
