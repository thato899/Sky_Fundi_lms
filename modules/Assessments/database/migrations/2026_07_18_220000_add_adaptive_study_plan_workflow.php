<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_study_plans', function (Blueprint $table): void {
            $table->index('quiz_attempt_id', 'quiz_study_plan_attempt_idx');
            $table->index('organization_id', 'quiz_study_plan_organization_idx');
            $table->index('learner_profile_id', 'quiz_study_plan_learner_idx');
            $table->dropUnique(['quiz_attempt_id']);
            $table->unsignedSmallInteger('version')->default(1)->after('learner_profile_id');
            $table->string('provider')->nullable()->after('content');
            $table->string('model')->nullable()->after('provider');
            $table->unsignedTinyInteger('completion_percentage')->default(0)->after('approved_at');
            $table->unsignedInteger('time_spent_minutes')->default(0)->after('completion_percentage');
            $table->json('completed_activities')->nullable()->after('time_spent_minutes');
            $table->json('mastered_concepts')->nullable()->after('completed_activities');
            $table->json('remaining_concepts')->nullable()->after('mastered_concepts');
            $table->timestamp('last_activity_at')->nullable()->after('remaining_concepts');
            $table->timestamp('completed_at')->nullable()->after('last_activity_at');
            $table->timestamp('published_at')->nullable()->after('completed_at');
            $table->foreignUuid('published_by')->nullable()->after('published_at')->constrained('users')->nullOnDelete();
            $table->unique(['quiz_attempt_id', 'version'], 'quiz_study_plan_attempt_version_unique');
            $table->index(['organization_id', 'learner_profile_id', 'status'], 'quiz_study_plan_learner_status_idx');
        });

        Schema::create('quiz_revision_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('quiz_study_plan_id')->constrained('quiz_study_plans')->restrictOnDelete();
            $table->foreignUuid('learner_profile_id')->constrained('learner_profiles')->restrictOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->json('responses');
            $table->json('evaluation')->nullable();
            $table->decimal('score_percentage', 7, 2)->nullable();
            $table->string('status', 24)->default('submitted');
            $table->timestamp('submitted_at');
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();
            $table->unique(['quiz_study_plan_id', 'attempt_number'], 'quiz_revision_plan_attempt_unique');
            $table->index(['organization_id', 'learner_profile_id', 'status'], 'quiz_revision_learner_status_idx');
        });

        DB::table('quiz_study_plans')->where('status', 'approved')->update([
            'status' => 'published',
            'published_at' => DB::raw('approved_at'),
            'published_by' => DB::raw('approved_by'),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_revision_attempts');

        Schema::table('quiz_study_plans', function (Blueprint $table): void {
            $table->dropUnique('quiz_study_plan_attempt_version_unique');
            $table->dropIndex('quiz_study_plan_learner_status_idx');
            $table->dropConstrainedForeignId('published_by');
            $table->dropColumn([
                'version',
                'provider',
                'model',
                'completion_percentage',
                'time_spent_minutes',
                'completed_activities',
                'mastered_concepts',
                'remaining_concepts',
                'last_activity_at',
                'completed_at',
                'published_at',
            ]);
            $table->unique('quiz_attempt_id');
            $table->dropIndex('quiz_study_plan_attempt_idx');
        });
    }
};
