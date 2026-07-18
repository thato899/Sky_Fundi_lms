<?php

declare(strict_types=1);

namespace Modules\Assessments\Tests\Feature;

use Core\Identity\Infrastructure\Models\Membership;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Modules\Academics\Infrastructure\Models\AcademicTerm;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Assessments\Application\AssessmentCategoryService;
use Modules\Assessments\Application\AssessmentService;
use Modules\Assessments\Application\InterventionDashboardService;
use Modules\Assessments\Database\Seeders\AssessmentsPermissionSeeder;
use Modules\Assessments\Infrastructure\Models\AssessmentQuestion;
use Modules\Assessments\Infrastructure\Models\QuizAttempt;
use Modules\Assessments\Infrastructure\Models\QuizRevisionAttempt;
use Modules\Assessments\Infrastructure\Models\QuizStudyPlan;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Staff\Infrastructure\Models\StaffProfile;
use Tests\TestCase;

final class InterventionDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_intervention_dashboard_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('quizzes.interventions'));
        $this->assertContains('api/v1/interventions/dashboard', collect(Route::getRoutes())->map->uri()->all());
        $this->assertContains('api/v1/interventions/recommendations', collect(Route::getRoutes())->map->uri()->all());
    }

    public function test_teacher_permissions_are_scoped_below_organization_aggregate_access(): void
    {
        $this->seed(AssessmentsPermissionSeeder::class);

        $teacher = Role::query()->where('name', 'Teacher')->firstOrFail();
        $administrator = Role::query()->where('name', 'Organization Administrator')->firstOrFail();
        $learner = Role::query()->where('name', 'Learner')->firstOrFail();

        $this->assertTrue($teacher->permissions()->where('name', 'interventions.view')->exists());
        $this->assertTrue($teacher->permissions()->where('name', 'interventions.manage')->exists());
        $this->assertFalse($teacher->permissions()->where('name', 'interventions.view_organization')->exists());
        $this->assertTrue($administrator->permissions()->where('name', 'interventions.view_organization')->exists());
        $this->assertFalse($learner->permissions()->where('name', 'interventions.view')->exists());
    }

    public function test_dashboard_classifies_risk_orders_queue_and_deduplicates_attempts(): void
    {
        $context = $this->context();
        $learners = collect(['Red', 'Orange', 'Yellow', 'Green'])->map(fn (string $name) => LearnerProfile::factory()->create([
            'organization_id' => $context['organization']->id,
            'current_academic_year_id' => $context['year']->id,
            'current_grade_id' => $context['grade']->id,
            'current_class_id' => $context['class']->id,
            'first_name' => $name,
            'last_name' => 'Learner',
            'learner_status' => 'active',
        ]));
        $assessment = app(AssessmentService::class)->create($context['organization'], $context['owner'], [
            'academic_year_id' => $context['year']->id, 'academic_term_id' => $context['term']->id,
            'grade_id' => $context['grade']->id, 'class_id' => $context['class']->id,
            'subject_id' => $context['subject']->id, 'assessment_category_id' => $context['category']->id,
            'staff_profile_id' => $context['staff']->id, 'title' => 'Risk quiz', 'maximum_mark' => 100,
        ]);
        $question = AssessmentQuestion::query()->create([
            'organization_id' => $context['organization']->id, 'assessment_id' => $assessment->id,
            'type' => 'short_response', 'prompt' => 'Fractions', 'marks_available' => 100,
            'display_order' => 1, 'key_concepts' => ['Fractions'],
        ]);

        $red = $this->releasedAttempt($assessment, $question, $learners[0], 30, 0, ['Fractions', 'Ratios', 'Decimals'], true, null, 1);
        $this->releasedAttempt($assessment, $question, $learners[0], 90, 100, [], false, 90, 2);
        $orange = $this->releasedAttempt($assessment, $question, $learners[1], 55, 50, ['Fractions'], false, null, 1);
        $yellow = $this->releasedAttempt($assessment, $question, $learners[2], 70, 100, [], true, null, 1);
        $green = $this->releasedAttempt($assessment, $question, $learners[3], 90, 100, [], false, 90, 1);

        $dashboard = app(InterventionDashboardService::class)->dashboard($context['organization']->id, $context['teacher']);
        $levels = collect($dashboard['attempts'])->keyBy('attempt_id')->pluck('risk_level', 'attempt_id');

        $this->assertSame('red', $levels[$red->id]);
        $this->assertSame('orange', $levels[$orange->id]);
        $this->assertSame('yellow', $levels[$yellow->id]);
        $this->assertSame('green', $levels[$green->id]);
        $this->assertSame(5, $dashboard['overview']['released_attempts']);
        $this->assertSame(4, $dashboard['overview']['learners']);
        $this->assertSame(61.3, $dashboard['overview']['average_class_mark']);
        $this->assertCount(2, $dashboard['intervention_queue']);
        $this->assertSame(['red', 'orange'], collect($dashboard['intervention_queue'])->pluck('risk_level')->all());
        $this->assertSame(1, collect($dashboard['intervention_queue'])->where('learner_id', $learners[0]->id)->count());
        $this->assertSame(4, collect($dashboard['weak_concepts'])->firstWhere('concept', 'Fractions')['affected_learners']);
        $this->assertTrue(collect($dashboard['learners'])->firstWhere('learner_id', $learners[3]->id)['ready_for_reassessment']);
        $this->assertContains(100, collect($dashboard['mastery'])->where('learner', 'Green Learner')->pluck('mastery_percentage'));

        $this->assertSame(0, app(InterventionDashboardService::class)->dashboard($context['organization']->id, User::factory()->create())['overview']['learners']);
        $this->assertSame(4, app(InterventionDashboardService::class)->dashboard($context['organization']->id, User::factory()->create(), true)['overview']['learners']);

        config(['ai.default_provider' => 'openai', 'ai.fallback_provider' => null, 'ai.providers.openai.enabled' => true, 'ai.providers.openai.api_key' => 'test-only', 'ai.providers.openai.base_url' => 'https://api.openai.test/v1']);
        $validRecommendation = ['suggestions' => [['concept' => 'Fractions', 'action' => 'Use fraction strips.', 'estimated_minutes' => 20]]];
        $invalidRecommendation = ['suggestions' => [['concept' => '', 'action' => '', 'estimated_minutes' => 1]]];
        Http::fakeSequence('https://api.openai.test/v1/responses')
            ->push(['output_text' => json_encode($validRecommendation)])
            ->push(['output_text' => json_encode($invalidRecommendation)]);
        $recommendations = app(InterventionDashboardService::class)->recommendations($context['organization']->id, $context['teacher']);
        $this->assertSame('Use fraction strips.', $recommendations['suggestions'][0]['action']);
        $this->expectException(\UnexpectedValueException::class);
        app(InterventionDashboardService::class)->recommendations($context['organization']->id, $context['teacher']);
    }

    private function releasedAttempt($assessment, AssessmentQuestion $question, LearnerProfile $learner, int $score, int $completion, array $remaining, bool $adjusted, ?int $revisionScore, int $number): QuizAttempt
    {
        $result = $assessment->results()->where('learner_profile_id', $learner->id)->firstOrFail();
        $attempt = QuizAttempt::query()->create([
            'organization_id' => $assessment->organization_id, 'assessment_id' => $assessment->id,
            'assessment_result_id' => $result->id, 'learner_profile_id' => $learner->id,
            'attempt_number' => $number, 'status' => 'released', 'started_at' => now()->subDays(10),
            'submitted_at' => now()->subDays(9), 'reviewed_at' => now()->subDays(8),
            'reviewed_by' => $assessment->created_by, 'released_at' => now()->subDays(7 - $number),
            'released_by' => $assessment->created_by, 'final_score' => $score,
        ]);
        $attempt->answers()->create([
            'organization_id' => $assessment->organization_id, 'assessment_question_id' => $question->id,
            'marks_available' => 100, 'marks_awarded' => $score, 'teacher_adjusted' => $adjusted,
            'ai_feedback' => ['confidence' => 0.8],
        ]);
        $plan = QuizStudyPlan::query()->create([
            'organization_id' => $assessment->organization_id, 'quiz_attempt_id' => $attempt->id,
            'learner_profile_id' => $learner->id, 'version' => 1, 'status' => 'published',
            'content' => ['weak_concepts' => array_values(array_unique([...$remaining, 'Fractions'])), 'daily_schedule' => [], 'revision_exercises' => []],
            'completion_percentage' => $completion, 'remaining_concepts' => $remaining,
            'mastered_concepts' => $remaining === [] ? ['Fractions'] : [],
            'last_activity_at' => $score === 30 ? now()->subDays(10) : now(),
            'published_at' => now()->subDays(7),
        ]);
        if ($revisionScore !== null) {
            QuizRevisionAttempt::query()->create([
                'organization_id' => $assessment->organization_id, 'quiz_study_plan_id' => $plan->id,
                'learner_profile_id' => $learner->id, 'attempt_number' => 1, 'responses' => ['a'],
                'evaluation' => ['ok' => true], 'score_percentage' => $revisionScore, 'status' => 'evaluated',
                'submitted_at' => now(), 'evaluated_at' => now(),
            ]);
        }

        return $attempt;
    }

    private function context(): array
    {
        $this->seed(AssessmentsPermissionSeeder::class);
        $organization = Organization::query()->create(['name' => 'Intervention School', 'code' => 'INT', 'type' => 'school']);
        $teacher = User::factory()->create();
        $owner = User::factory()->create();
        $role = Role::query()->where('name', 'Teacher')->firstOrFail();
        $membership = Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $teacher->id, 'role_id' => $role->id, 'status' => 'active']);
        $staff = StaffProfile::query()->create(['organization_id' => $organization->id, 'organization_membership_id' => $membership->id, 'user_id' => $teacher->id, 'employee_number' => 'T-1', 'first_name' => 'Test', 'last_name' => 'Teacher', 'staff_type' => 'teacher', 'employment_status' => 'active']);
        $year = AcademicYear::query()->create(['organization_id' => $organization->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $term = AcademicTerm::query()->create(['organization_id' => $organization->id, 'academic_year_id' => $year->id, 'term_number' => 1, 'name' => 'Term 1', 'start_date' => '2026-01-01', 'end_date' => '2026-03-31']);
        $grade = Grade::query()->create(['organization_id' => $organization->id, 'academic_year_id' => $year->id, 'name' => 'Grade 8', 'order' => 1]);
        $class = ClassGroup::query()->create(['organization_id' => $organization->id, 'academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => '8A']);
        $subject = Subject::query()->create(['organization_id' => $organization->id, 'name' => 'Mathematics', 'code' => 'MATH']);
        $category = app(AssessmentCategoryService::class)->create($organization, $owner, ['name' => 'Quiz', 'code' => 'QUIZ']);

        return compact('organization', 'teacher', 'owner', 'staff', 'year', 'term', 'grade', 'class', 'subject', 'category');
    }
}
