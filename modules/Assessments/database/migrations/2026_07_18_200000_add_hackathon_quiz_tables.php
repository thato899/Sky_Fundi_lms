<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->timestamp('opens_at')->nullable()->after('instructions');
            $table->timestamp('closes_at')->nullable()->after('opens_at');
            $table->unsignedSmallInteger('time_limit_minutes')->nullable()->after('closes_at');
            $table->unsignedSmallInteger('attempt_limit')->default(1)->after('time_limit_minutes');
        });

        Schema::create('assessment_questions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->string('type', 32);
            $table->text('prompt');
            $table->decimal('marks_available', 10, 2);
            $table->unsignedSmallInteger('display_order');
            $table->text('model_answer')->nullable();
            $table->text('marking_guidance')->nullable();
            $table->json('key_concepts')->nullable();
            $table->timestamps();
            $table->unique(['assessment_id', 'display_order'], 'assessment_question_order_unique');
            $table->index(['organization_id', 'assessment_id'], 'assessment_question_org_assessment_idx');
        });

        Schema::create('assessment_question_options', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('assessment_question_id')->constrained('assessment_questions')->cascadeOnDelete();
            $table->string('label', 500);
            $table->boolean('is_correct')->default(false);
            $table->unsignedSmallInteger('display_order');
            $table->timestamps();
            $table->unique(['assessment_question_id', 'display_order'], 'assessment_option_order_unique');
        });

        Schema::create('quiz_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('assessment_id')->constrained('assessments')->restrictOnDelete();
            $table->foreignUuid('assessment_result_id')->constrained('assessment_results')->restrictOnDelete();
            $table->foreignUuid('learner_profile_id')->constrained('learner_profiles')->restrictOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->string('status', 24)->default('in_progress');
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->decimal('final_score', 10, 2)->nullable();
            $table->timestamps();
            $table->unique(['assessment_id', 'learner_profile_id', 'attempt_number'], 'quiz_attempt_number_unique');
            $table->index(['organization_id', 'learner_profile_id', 'status'], 'quiz_attempt_learner_status_idx');
        });

        Schema::create('quiz_answers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('quiz_attempt_id')->constrained('quiz_attempts')->cascadeOnDelete();
            $table->foreignUuid('assessment_question_id')->constrained('assessment_questions')->restrictOnDelete();
            $table->foreignUuid('selected_option_id')->nullable()->constrained('assessment_question_options')->restrictOnDelete();
            $table->longText('answer_text')->nullable();
            $table->decimal('marks_available', 10, 2);
            $table->decimal('ai_suggested_mark', 10, 2)->nullable();
            $table->decimal('marks_awarded', 10, 2)->nullable();
            $table->string('marking_method', 24)->nullable();
            $table->json('ai_feedback')->nullable();
            $table->text('teacher_feedback')->nullable();
            $table->foreignUuid('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('marked_at')->nullable();
            $table->boolean('teacher_adjusted')->default(false);
            $table->timestamps();
            $table->unique(['quiz_attempt_id', 'assessment_question_id'], 'quiz_answer_question_unique');
        });

        Schema::create('ai_grading_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('quiz_attempt_id')->constrained('quiz_attempts')->restrictOnDelete();
            $table->foreignUuid('quiz_answer_id')->nullable()->constrained('quiz_answers')->restrictOnDelete();
            $table->string('request_type', 32);
            $table->string('idempotency_key')->unique();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('estimated_cost', 12, 6)->default(0);
            $table->text('failure_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'request_type', 'created_at'], 'ai_grading_org_type_date_idx');
        });

        Schema::create('quiz_study_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('quiz_attempt_id')->constrained('quiz_attempts')->restrictOnDelete();
            $table->foreignUuid('learner_profile_id')->constrained('learner_profiles')->restrictOnDelete();
            $table->json('content');
            $table->string('status', 24)->default('draft');
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique('quiz_attempt_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_study_plans');
        Schema::dropIfExists('ai_grading_requests');
        Schema::dropIfExists('quiz_answers');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('assessment_question_options');
        Schema::dropIfExists('assessment_questions');

        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropColumn(['opens_at', 'closes_at', 'time_limit_minutes', 'attempt_limit']);
        });
    }
};
