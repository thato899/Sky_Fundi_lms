<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Class" is a reserved word in PHP — the table/model are named
 * ClassGroup throughout this module (see
 * Infrastructure/Models/ClassGroup.php) even though the table itself
 * is named plainly. `is_homeroom` is the "Homeroom Ready" flag from
 * the brief; `capacity` and future timetable linkage are deliberately
 * simple today — see modules/Academics/README.md#classes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academics_classes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->unsignedInteger('capacity')->nullable();
            $table->foreignUuid('academic_year_id')->constrained('academics_academic_years')->cascadeOnDelete();
            $table->foreignUuid('grade_id')->constrained('academics_grades')->cascadeOnDelete();
            $table->boolean('is_homeroom')->default(false);
            $table->string('status')->default('active');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['academic_year_id', 'grade_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academics_classes');
    }
};
