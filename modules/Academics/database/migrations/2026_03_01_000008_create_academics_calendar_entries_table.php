<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A single entries table covering School Days, Public Holidays, Exam
 * Periods, Assessment Periods, and Events (see `type`) rather than
 * five separate tables — see modules/Academics/README.md#calendar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academics_calendar_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('academic_year_id')->constrained('academics_academic_years')->cascadeOnDelete();
            $table->string('type');
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['academic_year_id', 'type']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academics_calendar_entries');
    }
};
