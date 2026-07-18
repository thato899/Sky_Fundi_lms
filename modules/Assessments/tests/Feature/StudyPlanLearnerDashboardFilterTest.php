<?php

declare(strict_types=1);

namespace Modules\Assessments\Tests\Feature;

use Core\RBAC\Infrastructure\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Assessments\Database\Seeders\AssessmentsPermissionSeeder;
use Tests\TestCase;

final class StudyPlanLearnerDashboardFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_adaptive_schema_preserves_versioned_plans_and_revision_history(): void
    {
        $this->assertTrue(Schema::hasColumns('quiz_study_plans', ['version', 'completion_percentage', 'time_spent_minutes', 'completed_activities', 'mastered_concepts', 'remaining_concepts', 'published_at', 'published_by']));
        $this->assertTrue(Schema::hasColumns('quiz_revision_attempts', ['quiz_study_plan_id', 'learner_profile_id', 'responses', 'evaluation', 'score_percentage', 'submitted_at', 'evaluated_at']));
    }

    public function test_learner_dashboard_and_study_plan_routes_are_registered(): void
    {
        foreach (['quizzes.assigned', 'quizzes.study-plan.progress', 'quizzes.study-plan.retest', 'quizzes.study-plan.analytics'] as $name) {
            $this->assertTrue(Route::has($name));
        }
    }

    public function test_study_plan_permissions_separate_teacher_analytics_from_learner_progress(): void
    {
        $this->seed(AssessmentsPermissionSeeder::class);

        $this->assertTrue(Role::query()->where('name', 'Teacher')->firstOrFail()->permissions()->where('name', 'study_plans.analytics')->exists());
        $this->assertFalse(Role::query()->where('name', 'Learner')->firstOrFail()->permissions()->where('name', 'study_plans.analytics')->exists());
        $this->assertTrue(Role::query()->where('name', 'Learner')->firstOrFail()->permissions()->where('name', 'quiz_attempts.submit')->exists());
    }
}
