<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academics_timetable_periods', function (Blueprint $table): void {
            $table->string('code', 64)->nullable()->after('name');
            $table->unique(['organization_id', 'code'], 'academic_period_org_code_unique');
        });
        Schema::table('academics_calendar_entries', function (Blueprint $table): void {
            $table->foreignUuid('academic_term_id')->nullable()->after('academic_year_id')->constrained('academics_academic_terms')->nullOnDelete();
            $table->boolean('affects_teaching')->default(false)->after('description');
            $table->string('closure_scope', 24)->default('none')->after('affects_teaching');
            $table->foreignUuid('grade_id')->nullable()->constrained('academics_grades')->nullOnDelete();
            $table->foreignUuid('class_id')->nullable()->constrained('academics_classes')->nullOnDelete();
            $table->string('status', 24)->default('active');
            $table->index(['organization_id', 'start_date', 'end_date', 'affects_teaching'], 'calendar_closure_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::table('academics_calendar_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('class_id');
            $table->dropConstrainedForeignId('grade_id');
            $table->dropConstrainedForeignId('academic_term_id');
            $table->dropColumn(['affects_teaching', 'closure_scope', 'status']);
        });
        Schema::table('academics_timetable_periods', function (Blueprint $table): void {
            $table->dropColumn('code');
        });
    }
};
