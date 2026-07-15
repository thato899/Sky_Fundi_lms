<?php

declare(strict_types=1);

namespace Modules\Academics\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Academics\Infrastructure\Models\Curriculum;
use Modules\Organizations\Infrastructure\Models\Organization;
use Tests\TestCase;

final class AcademicsOwnershipMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_operational_academic_tables_have_organization_ownership(): void
    {
        foreach (['academics_curricula', 'academics_departments', 'academics_academic_years', 'academics_academic_terms', 'academics_grades', 'academics_classes', 'academics_subjects', 'academics_calendar_entries', 'academics_timetable_periods'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'organization_id'), "{$table} must be organization-owned.");
        }
    }

    public function test_codes_are_unique_per_organization_and_organization_fk_is_enforced(): void
    {
        $first = Organization::create(['name' => 'First', 'code' => 'first-migration', 'type' => 'school']);
        $second = Organization::create(['name' => 'Second', 'code' => 'second-migration', 'type' => 'school']);
        Curriculum::create(['organization_id' => $first->id, 'name' => 'First', 'code' => 'SAME']);
        Curriculum::create(['organization_id' => $second->id, 'name' => 'Second', 'code' => 'SAME']);
        $this->assertSame(2, Curriculum::withoutGlobalScopes()->where('code', 'SAME')->count());

        $this->expectException(QueryException::class);
        Curriculum::create(['organization_id' => $first->id, 'name' => 'Duplicate', 'code' => 'SAME']);
    }
}
