<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('academic_year_id')->constrained('academics_academic_years')->restrictOnDelete();
            $table->foreignUuid('academic_term_id')->nullable()->constrained('academics_academic_terms')->nullOnDelete();
            $table->foreignUuid('class_id')->constrained('academics_classes')->restrictOnDelete();
            $table->foreignUuid('subject_id')->nullable()->constrained('academics_subjects')->nullOnDelete();
            $table->foreignUuid('timetable_period_id')->nullable()->constrained('academics_timetable_periods')->nullOnDelete();
            $table->foreignUuid('staff_profile_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->date('session_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('session_type', 32);
            $table->string('title')->nullable();
            $table->string('status', 24)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignUuid('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignUuid('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reopen_reason')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'session_date', 'status']);
            $table->index(['organization_id', 'class_id', 'session_date']);
        });

        Schema::create('attendance_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('attendance_session_id')->constrained('attendance_sessions')->restrictOnDelete();
            $table->foreignUuid('learner_profile_id')->constrained('learner_profiles')->restrictOnDelete();
            $table->string('status', 24)->default('not_recorded');
            $table->time('arrival_time')->nullable();
            $table->time('departure_time')->nullable();
            $table->unsignedSmallInteger('minutes_late')->nullable();
            $table->string('reason', 500)->nullable();
            $table->string('note', 1000)->nullable();
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'attendance_session_id', 'learner_profile_id'], 'attendance_entry_learner_unique');
            $table->index(['organization_id', 'learner_profile_id', 'status'], 'attendance_entry_learner_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_entries');
        Schema::dropIfExists('attendance_sessions');
    }
};
