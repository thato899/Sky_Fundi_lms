<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduling_rooms', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('name');
            $table->string('code', 64)->nullable();
            $table->string('location_type', 24);
            $table->unsignedInteger('capacity')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('online_url')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
            $table->unique(['organization_id', 'code']);
        });
        Schema::create('timetable_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('academic_year_id')->constrained('academics_academic_years')->restrictOnDelete();
            $table->foreignUuid('academic_term_id')->nullable()->constrained('academics_academic_terms')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 24)->default('draft');
            $table->date('effective_start_date');
            $table->date('effective_end_date');
            $table->boolean('is_active')->default(false);
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'effective_start_date', 'effective_end_date'], 'template_scope_idx');
        });
        Schema::create('timetable_template_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('timetable_template_id')->constrained('timetable_templates')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->foreignUuid('teaching_period_id')->nullable()->constrained('academics_timetable_periods')->restrictOnDelete();
            $table->time('start_time');
            $table->time('end_time');
            $table->foreignUuid('grade_id')->constrained('academics_grades')->restrictOnDelete();
            $table->foreignUuid('class_id')->constrained('academics_classes')->restrictOnDelete();
            $table->foreignUuid('subject_id')->constrained('academics_subjects')->restrictOnDelete();
            $table->foreignUuid('room_id')->nullable()->constrained('scheduling_rooms')->restrictOnDelete();
            $table->string('delivery_mode', 24);
            $table->string('status', 24)->default('active');
            $table->text('notes')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->index(['organization_id', 'weekday', 'start_time', 'end_time'], 'template_entry_overlap_idx');
        });
        Schema::create('scheduled_lessons', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('academic_year_id')->constrained('academics_academic_years')->restrictOnDelete();
            $table->foreignUuid('academic_term_id')->nullable()->constrained('academics_academic_terms')->restrictOnDelete();
            $table->foreignUuid('timetable_template_entry_id')->nullable()->constrained('timetable_template_entries')->nullOnDelete();
            $table->foreignUuid('grade_id')->constrained('academics_grades')->restrictOnDelete();
            $table->foreignUuid('class_id')->constrained('academics_classes')->restrictOnDelete();
            $table->foreignUuid('subject_id')->constrained('academics_subjects')->restrictOnDelete();
            $table->foreignUuid('room_id')->nullable()->constrained('scheduling_rooms')->nullOnDelete();
            $table->date('lesson_date');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('delivery_mode', 24);
            $table->string('status', 24)->default('scheduled');
            $table->string('title')->nullable();
            $table->text('lesson_objective')->nullable();
            $table->text('lesson_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignUuid('rescheduled_from_lesson_id')->nullable()->constrained('scheduled_lessons')->nullOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['timetable_template_entry_id', 'lesson_date'], 'lesson_materialization_unique');
            $table->unique(['id', 'organization_id'], 'lesson_id_org_unique');
            $table->index(['organization_id', 'lesson_date', 'status']);
            $table->index(['organization_id', 'class_id', 'starts_at', 'ends_at'], 'lesson_class_overlap_idx');
        });
        Schema::create('scheduled_lesson_staff', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('scheduled_lesson_id');
            $table->uuid('staff_profile_id');
            $table->string('assignment_type', 24);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->foreign(['scheduled_lesson_id', 'organization_id'], 'lesson_staff_lesson_org_fk')->references(['id', 'organization_id'])->on('scheduled_lessons')->cascadeOnDelete();
            $table->foreign('staff_profile_id')->references('id')->on('staff_profiles')->restrictOnDelete();
            $table->unique(['scheduled_lesson_id', 'staff_profile_id'], 'lesson_staff_unique');
            $table->index(['organization_id', 'staff_profile_id']);
        });
        Schema::create('schedule_change_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('scheduled_lesson_id')->constrained('scheduled_lessons')->restrictOnDelete();
            $table->string('action', 64);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->foreignUuid('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'scheduled_lesson_id', 'created_at'], 'schedule_history_idx');
        });
        Schema::table('attendance_sessions', function (Blueprint $table): void {
            $table->foreignUuid('scheduled_lesson_id')->nullable()->after('organization_id')->constrained('scheduled_lessons')->nullOnDelete();
            $table->unique('scheduled_lesson_id');
        });
        Schema::table('assessments', function (Blueprint $table): void {
            $table->foreignUuid('scheduled_lesson_id')->nullable()->after('organization_id')->constrained('scheduled_lessons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assessments', fn (Blueprint $table) => $table->dropConstrainedForeignId('scheduled_lesson_id'));
        Schema::table('attendance_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('scheduled_lesson_id');
        });
        Schema::dropIfExists('schedule_change_logs');
        Schema::dropIfExists('scheduled_lesson_staff');
        Schema::dropIfExists('scheduled_lessons');
        Schema::dropIfExists('timetable_template_entries');
        Schema::dropIfExists('timetable_templates');
        Schema::dropIfExists('scheduling_rooms');
    }
};
