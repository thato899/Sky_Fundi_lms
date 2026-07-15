<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'academics_curricula', 'academics_departments', 'academics_academic_years',
        'academics_academic_terms', 'academics_grades', 'academics_classes',
        'academics_subjects', 'academics_calendar_entries', 'academics_timetable_periods',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreignUuid('organization_id')->nullable()->after('id')->constrained('organizations')->restrictOnDelete();
            });
        }

        $hasAcademics = collect(self::TABLES)->contains(fn (string $table): bool => DB::table($table)->exists());
        if ($hasAcademics) {
            $organizations = DB::table('organizations')->pluck('id');
            if ($organizations->count() !== 1) {
                throw new RuntimeException('Academics ownership cannot be derived safely. Existing academic rows require exactly one bootstrap organization; assign ownership explicitly before rerunning this migration.');
            }
            foreach (self::TABLES as $table) {
                DB::table($table)->whereNull('organization_id')->update(['organization_id' => $organizations->first()]);
            }
        }

        foreach (self::TABLES as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->uuid('organization_id')->nullable(false)->change());
        }

        Schema::table('academics_curricula', function (Blueprint $table): void {
            $table->dropUnique('academics_curricula_code_unique');
            $table->unique(['organization_id', 'code']);
        });
        Schema::table('academics_departments', function (Blueprint $table): void {
            $table->dropUnique('academics_departments_code_unique');
            $table->unique(['organization_id', 'code']);
        });
        Schema::table('academics_subjects', function (Blueprint $table): void {
            $table->dropUnique('academics_subjects_code_unique');
            $table->unique(['organization_id', 'code']);
        });
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropForeign(['organization_id']));
        }
        Schema::table('academics_subjects', function (Blueprint $table): void {
            $table->dropUnique(['organization_id', 'code']);
            $table->unique('code');
        });
        Schema::table('academics_departments', function (Blueprint $table): void {
            $table->dropUnique(['organization_id', 'code']);
            $table->unique('code');
        });
        Schema::table('academics_curricula', function (Blueprint $table): void {
            $table->dropUnique(['organization_id', 'code']);
            $table->unique('code');
        });
        foreach (array_reverse(self::TABLES) as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('organization_id'));
        }
    }
};
