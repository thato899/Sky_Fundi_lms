<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_scales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('name');
            $table->string('code', 64)->nullable();
            $table->text('description')->nullable();
            $table->decimal('pass_threshold', 7, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
            $table->index(['organization_id', 'is_active', 'is_default']);
        });
        Schema::create('grading_scale_bands', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('grading_scale_id')->constrained('grading_scales')->cascadeOnDelete();
            $table->string('label');
            $table->string('code', 64)->nullable();
            $table->decimal('minimum_percentage', 7, 2);
            $table->decimal('maximum_percentage', 7, 2);
            $table->string('symbol', 32)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_passing')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'grading_scale_id', 'display_order'], 'grading_band_order_idx');
        });
        Schema::create('reporting_periods', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('academic_year_id')->constrained('academics_academic_years')->restrictOnDelete();
            $table->foreignUuid('academic_term_id')->nullable()->constrained('academics_academic_terms')->restrictOnDelete();
            $table->string('name');
            $table->string('code', 64)->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->date('result_cutoff_date')->nullable();
            $table->string('status', 24)->default('draft');
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'academic_year_id', 'name'], 'report_period_org_year_name_unique');
            $table->index(['organization_id', 'status', 'start_date', 'end_date'], 'report_period_org_status_dates_idx');
        });
        Schema::create('report_card_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            foreach (['show_attendance', 'show_assessment_breakdown', 'show_subject_comments', 'show_overall_comment', 'show_grading_legend', 'show_learner_photo', 'show_organization_logo'] as $column) {
                $table->boolean($column)->default($column !== 'show_learner_photo');
            }
            $table->text('footer_text')->nullable();
            $table->string('page_size', 16)->default('A4');
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'name'], 'report_template_org_name_unique');
            $table->index(['organization_id', 'is_active', 'is_default'], 'report_template_org_state_idx');
        });
        Schema::create('report_cards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('learner_profile_id')->constrained('learner_profiles')->restrictOnDelete();
            $table->foreignUuid('academic_year_id')->constrained('academics_academic_years')->restrictOnDelete();
            $table->foreignUuid('academic_term_id')->nullable()->constrained('academics_academic_terms')->restrictOnDelete();
            $table->foreignUuid('reporting_period_id')->constrained('reporting_periods')->restrictOnDelete();
            $table->foreignUuid('report_card_template_id')->constrained('report_card_templates')->restrictOnDelete();
            $table->foreignUuid('class_id')->constrained('academics_classes')->restrictOnDelete();
            $table->foreignUuid('grade_id')->constrained('academics_grades')->restrictOnDelete();
            $table->foreignUuid('grading_scale_id')->constrained('grading_scales')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 24)->default('draft');
            $table->timestamp('generated_at');
            $table->foreignUuid('generated_by')->constrained('users')->restrictOnDelete();
            foreach (['reviewed', 'approved', 'published', 'withdrawn'] as $action) {
                $table->timestamp($action.'_at')->nullable();
                $table->foreignUuid($action.'_by')->nullable()->constrained('users')->nullOnDelete();
            }
            $table->text('withdrawal_reason')->nullable();
            $table->decimal('overall_average', 7, 2)->nullable();
            foreach (['attendance_session_count', 'present_count', 'absent_count', 'late_count', 'excused_count', 'remote_count'] as $column) {
                $table->unsignedInteger($column)->default(0);
            }
            $table->text('overall_comment')->nullable();
            $table->json('snapshot_metadata')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'learner_profile_id', 'reporting_period_id', 'version_number'], 'report_card_learner_period_version_unique');
            $table->index(['organization_id', 'reporting_period_id', 'status']);
        });
        Schema::create('report_card_subject_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('report_card_id')->constrained('report_cards')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->nullable()->constrained('academics_subjects')->nullOnDelete();
            $table->string('subject_name_snapshot');
            $table->string('subject_code_snapshot', 64)->nullable();
            $table->unsignedInteger('marked_assessment_count')->default(0);
            $table->decimal('total_valid_weighting', 7, 2)->nullable();
            $table->decimal('calculated_percentage', 7, 2)->nullable();
            $table->string('grading_band_label')->nullable();
            $table->string('grading_band_symbol', 32)->nullable();
            $table->string('subject_result_status', 24);
            $table->text('teacher_comment')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->unique(['report_card_id', 'subject_id']);
            $table->index(['organization_id', 'report_card_id', 'display_order'], 'report_subject_order_idx');
        });
        Schema::create('report_card_comments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('report_card_id')->constrained('report_cards')->cascadeOnDelete();
            $table->string('comment_type', 24);
            $table->text('comment');
            $table->foreignUuid('author_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('staff_profile_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'report_card_id', 'comment_type'], 'report_comment_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_card_comments');
        Schema::dropIfExists('report_card_subject_results');
        Schema::dropIfExists('report_cards');
        Schema::dropIfExists('report_card_templates');
        Schema::dropIfExists('reporting_periods');
        Schema::dropIfExists('grading_scale_bands');
        Schema::dropIfExists('grading_scales');
    }
};
