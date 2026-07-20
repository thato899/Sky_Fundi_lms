<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_teaching_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('staff_profile_id')->constrained('staff_profiles')->cascadeOnDelete();
            $table->foreignUuid('class_id')->constrained('academics_classes')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->nullable()->constrained('academics_subjects')->nullOnDelete();
            $table->foreignUuid('academic_year_id')->constrained('academics_academic_years')->cascadeOnDelete();
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unsignedTinyInteger('is_open')->nullable()->storedAs('case when `ended_on` is null then 1 else null end');
            // NULL subject_id rows escape this unique index (MySQL semantics);
            // TeachingAssignmentService closes that gap under a row lock.
            $table->unique(['staff_profile_id', 'class_id', 'subject_id', 'is_open'], 'staff_one_open_assignment_unique');
            $table->index(['organization_id', 'staff_profile_id', 'started_on'], 'staff_assignment_lookup');
            $table->index(['organization_id', 'class_id'], 'staff_assignment_class_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_teaching_assignments');
    }
};
