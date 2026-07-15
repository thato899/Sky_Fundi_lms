<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('name');
            $table->string('code', 64)->nullable();
            $table->text('description')->nullable();
            $table->decimal('default_weighting', 7, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'name'], 'assessment_category_org_name_unique');
            $table->unique(['organization_id', 'code'], 'assessment_category_org_code_unique');
            $table->index(['organization_id', 'is_active', 'display_order'], 'assessment_category_org_active_order_idx');
        });

        Schema::create('assessments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('academic_year_id')->constrained('academics_academic_years')->restrictOnDelete();
            $table->foreignUuid('academic_term_id')->constrained('academics_academic_terms')->restrictOnDelete();
            $table->foreignUuid('grade_id')->constrained('academics_grades')->restrictOnDelete();
            $table->foreignUuid('class_id')->constrained('academics_classes')->restrictOnDelete();
            $table->foreignUuid('subject_id')->constrained('academics_subjects')->restrictOnDelete();
            $table->foreignUuid('assessment_category_id')->constrained('assessment_categories')->restrictOnDelete();
            $table->foreignUuid('staff_profile_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('assessment_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('maximum_mark', 10, 2);
            $table->decimal('weighting', 7, 4)->nullable();
            $table->string('status', 24)->default('draft');
            $table->string('result_release_status', 24)->default('withheld');
            $table->text('instructions')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignUuid('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignUuid('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reopen_reason')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignUuid('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'academic_year_id', 'academic_term_id', 'status'], 'assessment_org_period_status_idx');
            $table->index(['organization_id', 'class_id', 'subject_id'], 'assessment_org_class_subject_idx');
            $table->index(['organization_id', 'result_release_status', 'assessment_date'], 'assessment_org_release_date_idx');
        });

        Schema::create('assessment_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('assessment_id')->constrained('assessments')->restrictOnDelete();
            $table->foreignUuid('learner_profile_id')->constrained('learner_profiles')->restrictOnDelete();
            $table->decimal('score', 10, 2)->nullable();
            $table->decimal('percentage', 7, 2)->nullable();
            $table->string('result_status', 24)->default('pending');
            $table->text('feedback')->nullable();
            $table->text('private_note')->nullable();
            $table->foreignUuid('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('marked_at')->nullable();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'assessment_id', 'learner_profile_id'], 'assessment_result_learner_unique');
            $table->index(['organization_id', 'learner_profile_id', 'result_status'], 'assessment_result_learner_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_results');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('assessment_categories');
    }
};
