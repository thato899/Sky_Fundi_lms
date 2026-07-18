<?php

declare(strict_types=1);

namespace Modules\Assessments\Tests\Feature;

use Core\Identity\Infrastructure\Models\Membership;
use Core\Notifications\Infrastructure\Notifications\CoreNotification;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Modules\Academics\Infrastructure\Models\AcademicTerm;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Assessments\Application\AssessmentCategoryService;
use Modules\Assessments\Application\AssessmentService;
use Modules\Assessments\Application\QuizService;
use Modules\Assessments\Application\StudyPlanService;
use Modules\Assessments\Database\Seeders\AssessmentsPermissionSeeder;
use Modules\Assessments\Infrastructure\Models\AiGradingRequest;
use Modules\Assessments\Infrastructure\Models\Assessment;
use Modules\Assessments\Infrastructure\Models\QuizStudyPlan;
use Modules\Learners\Infrastructure\Models\GuardianProfile;
use Modules\Learners\Infrastructure\Models\LearnerGuardianRelationship;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Infrastructure\Models\OrganizationModule;
use Tests\TestCase;

final class QuizWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_objective_answers_are_marked_without_ai_and_submission_is_immutable(): void
    {
        Http::preventStrayRequests();
        $context = $this->context('objective');
        $quiz = $this->quiz($context);
        $service = app(QuizService::class);
        $question = $service->addQuestion($quiz, $context['teacher'], ['type' => 'multiple_choice', 'prompt' => '2 + 2?', 'marks_available' => 2, 'options' => [['label' => '3', 'is_correct' => false], ['label' => '4', 'is_correct' => true]]]);
        $service->publish($quiz->refresh(), $context['teacher']);
        $attempt = $service->start($quiz->refresh(), $context['learner']);
        $submitted = $service->submit($attempt, $context['learner'], [$question->uuid => ['selected_option_uuid' => $question->options->firstWhere('is_correct', true)->uuid]]);

        $this->assertSame('submitted', $submitted->status);
        $this->assertSame('automatic', $submitted->answers->first()->marking_method);
        $this->assertSame('2.00', $submitted->answers->first()->marks_awarded);
        $this->expectException(DomainException::class);
        $service->submit($submitted, $context['learner'], []);
    }

    public function test_unassigned_and_foreign_learner_cannot_start_quiz(): void
    {
        $context = $this->context('denied');
        $quiz = $this->quiz($context);
        app(QuizService::class)->addQuestion($quiz, $context['teacher'], ['type' => 'true_false', 'prompt' => 'True?', 'marks_available' => 1, 'options' => [['label' => 'True', 'is_correct' => true], ['label' => 'False', 'is_correct' => false]]]);
        app(QuizService::class)->publish($quiz->refresh(), $context['teacher']);
        $outsider = LearnerProfile::factory()->create(['organization_id' => $context['organization']->id, 'current_class_id' => null, 'learner_status' => 'active']);

        $this->expectException(DomainException::class);
        app(QuizService::class)->start($quiz->refresh(), $outsider);
    }

    public function test_structured_ai_mark_is_bounded_and_requires_teacher_review(): void
    {
        $context = $this->context('ai');
        $quiz = $this->quiz($context);
        $question = app(QuizService::class)->addQuestion($quiz, $context['teacher'], ['type' => 'short_response', 'prompt' => 'Explain equality.', 'marks_available' => 5, 'model_answer' => 'Both sides remain equal.', 'marking_guidance' => 'Award for balance and inverse operation.']);
        app(QuizService::class)->publish($quiz->refresh(), $context['teacher']);
        $attempt = app(QuizService::class)->start($quiz->refresh(), $context['learner']);
        $submitted = app(QuizService::class)->submit($attempt, $context['learner'], [$question->uuid => ['answer_text' => 'Do the same thing on both sides.']]);
        config(['ai.default_provider' => 'openai', 'ai.fallback_provider' => null, 'ai.providers.openai.enabled' => true, 'ai.providers.openai.api_key' => 'test-only', 'ai.providers.openai.base_url' => 'https://api.openai.test/v1']);
        Http::fake(['https://api.openai.test/v1/responses' => Http::response(['output_text' => json_encode(['awarded_marks' => 4, 'max_marks' => 5, 'criteria' => [['criterion' => 'Balance', 'met' => true, 'marks_awarded' => 4]], 'strengths' => ['Understands balance'], 'improvements' => ['Name the inverse operation'], 'misconceptions' => [], 'grading_rationale' => 'Correct core idea with one missing detail.', 'confidence' => 0.8, 'requires_teacher_review' => true]), 'usage' => ['input_tokens' => 100, 'output_tokens' => 60]], 200)]);

        $answer = app(QuizService::class)->suggestWrittenMark($submitted->answers->first(), $context['teacher']);
        $this->assertSame('4.00', $answer->ai_suggested_mark);
        $this->assertNull($answer->marks_awarded);
        $this->assertTrue($answer->ai_feedback['requires_teacher_review']);
        Http::assertSent(fn ($request) => ! str_contains($request->body(), 'residential_address') && ! str_contains($request->body(), 'guardian'));
    }

    public function test_ai_failure_preserves_submission_for_manual_marking_and_final_mark_is_bounded(): void
    {
        $context = $this->context('fallback');
        $quiz = $this->quiz($context);
        $question = app(QuizService::class)->addQuestion($quiz, $context['teacher'], ['type' => 'long_response', 'prompt' => 'Explain force.', 'marks_available' => 5]);
        app(QuizService::class)->publish($quiz->refresh(), $context['teacher']);
        $attempt = app(QuizService::class)->start($quiz->refresh(), $context['learner']);
        $attempt = app(QuizService::class)->submit($attempt, $context['learner'], [$question->uuid => ['answer_text' => 'Force changes motion.']]);
        config(['hackathon.ai.marking_enabled' => false]);
        try {
            app(QuizService::class)->suggestWrittenMark($attempt->answers->first(), $context['teacher']);
            $this->fail('AI-disabled grading did not fall back.');
        } catch (DomainException) {
        }
        $this->assertDatabaseHas('quiz_attempts', ['id' => $attempt->id, 'status' => 'submitted']);
        $this->expectException(DomainException::class);
        app(QuizService::class)->review($attempt->load('assessment'), $context['teacher'], [$attempt->answers->first()->uuid => ['marks_awarded' => 6]]);
    }

    public function test_teacher_can_save_draft_edit_and_approve_complete_written_marking(): void
    {
        $context = $this->context('review');
        [$attempt, $answer] = $this->writtenSubmission($context);
        $service = app(QuizService::class);

        $draft = $service->saveDraft($attempt->load('assessment'), $context['teacher'], [
            $answer->uuid => ['marks_awarded' => 3, 'teacher_feedback' => 'Draft feedback.'],
        ]);
        $this->assertSame('marking_draft', $draft->status);
        $this->assertNull($draft->final_score);
        $this->assertNull($draft->result->score);

        $approved = $service->review($draft->load('assessment'), $context['teacher'], [
            $answer->uuid => ['marks_awarded' => 4, 'teacher_feedback' => 'Clear idea; name the inverse operation next time.'],
        ]);
        $this->assertSame('reviewed', $approved->status);
        $this->assertSame('4.00', $approved->final_score);
        $this->assertSame('4.00', $approved->result->score);
        $this->assertSame($context['teacher']->id, $approved->reviewed_by);
        $this->assertNotNull($approved->reviewed_at);
        $this->assertSame('Clear idea; name the inverse operation next time.', $approved->answers->first()->teacher_feedback);
    }

    public function test_teacher_can_regenerate_ai_once_without_duplicate_answer_records(): void
    {
        $context = $this->context('regenerate');
        [$attempt, $answer] = $this->writtenSubmission($context);
        config(['ai.default_provider' => 'openai', 'ai.fallback_provider' => null, 'ai.providers.openai.enabled' => true, 'ai.providers.openai.api_key' => 'test-only', 'ai.providers.openai.base_url' => 'https://api.openai.test/v1']);
        Http::fakeSequence('https://api.openai.test/v1/responses')
            ->push($this->aiResponse(3))
            ->push($this->aiResponse(4));
        $service = app(QuizService::class);

        $service->suggestWrittenMark($answer, $context['teacher']);
        $regenerated = $service->suggestWrittenMark($answer->refresh(), $context['teacher'], true);

        $this->assertSame('4.00', $regenerated->ai_suggested_mark);
        $this->assertSame(1, $attempt->answers()->count());
        $this->assertSame(2, AiGradingRequest::query()->where('quiz_answer_id', $answer->id)->where('status', 'completed')->count());
        $this->expectException(DomainException::class);
        $service->suggestWrittenMark($regenerated, $context['teacher'], true);
    }

    public function test_release_requires_complete_approval_and_notifies_linked_learner_and_guardian(): void
    {
        Notification::fake();
        $context = $this->context('release');
        $learnerUser = User::factory()->create();
        $guardianUser = User::factory()->create();
        $context['learner']->update(['user_id' => $learnerUser->id, 'portal_access_enabled' => true]);
        $guardian = GuardianProfile::query()->create([
            'organization_id' => $context['organization']->id,
            'user_id' => $guardianUser->id,
            'first_name' => 'Parent',
            'last_name' => 'Release',
            'email' => $guardianUser->email,
            'status' => 'active',
            'created_by' => $context['teacher']->id,
        ]);
        LearnerGuardianRelationship::query()->create([
            'organization_id' => $context['organization']->id,
            'learner_profile_id' => $context['learner']->id,
            'guardian_profile_id' => $guardian->id,
            'relationship_type' => 'parent',
            'status' => 'active',
            'receives_academic_communication' => true,
            'created_by' => $context['teacher']->id,
        ]);
        [$attempt, $answer] = $this->writtenSubmission($context);
        $service = app(QuizService::class);

        try {
            $service->review($attempt->load('assessment'), $context['teacher'], [
                $answer->uuid => ['marks_awarded' => 4, 'teacher_feedback' => ''],
            ]);
            $this->fail('Incomplete feedback was approved.');
        } catch (DomainException) {
        }
        $this->assertSame('submitted', $attempt->refresh()->status);

        $approved = $service->review($attempt->load('assessment'), $context['teacher'], [
            $answer->uuid => ['marks_awarded' => 4, 'teacher_feedback' => 'Teacher-approved feedback.'],
        ]);
        $this->fakeAi($this->studyPlanResponse());
        $released = $service->release($approved->load('assessment'), $context['teacher']);

        $this->assertSame('released', $released->status);
        $this->assertSame($context['teacher']->id, $released->released_by);
        $this->assertNotNull($released->released_at);
        Notification::assertSentTo($learnerUser, CoreNotification::class);
        Notification::assertSentTo($guardianUser, CoreNotification::class);
    }

    public function test_study_plan_generation_and_regeneration_preserve_published_history(): void
    {
        Notification::fake();
        $context = $this->context('study-plan');
        [$attempt, $answer] = $this->writtenSubmission($context);
        $approved = app(QuizService::class)->review($attempt->load('assessment'), $context['teacher'], [
            $answer->uuid => ['marks_awarded' => 2, 'teacher_feedback' => 'Review equality and inverse operations.'],
        ]);
        $this->fakeAiSequence([$this->studyPlanResponse(), $this->studyPlanResponse('Use spaced equality practice.')]);
        app(QuizService::class)->release($approved->load('assessment'), $context['teacher']);

        $first = QuizStudyPlan::query()->where('quiz_attempt_id', $attempt->id)->firstOrFail();
        $this->assertSame(1, $first->version);
        $this->assertSame('published', $first->status);
        $this->assertSame(['Explain equality.'], $first->remaining_concepts);
        $this->assertSame('openai', $first->provider);
        $this->assertDatabaseHas('ai_grading_requests', ['quiz_attempt_id' => $attempt->id, 'request_type' => 'study_plan', 'status' => 'completed']);

        $draft = app(StudyPlanService::class)->generate($attempt->refresh(), $context['teacher'], regenerate: true);
        $this->assertSame(2, $draft->version);
        $this->assertSame('draft', $draft->status);
        $this->assertSame('published', $first->refresh()->status);

        $published = app(StudyPlanService::class)->publish($draft, $context['teacher']);
        $this->assertSame('published', $published->status);
        $this->assertSame('superseded', $first->refresh()->status);
        $this->assertDatabaseCount('quiz_study_plans', 2);
    }

    public function test_study_plan_generation_is_rejected_before_teacher_approval(): void
    {
        $context = $this->context('plan-too-early');
        [$attempt] = $this->writtenSubmission($context);

        $this->expectException(DomainException::class);
        app(StudyPlanService::class)->generate($attempt->load('assessment'), $context['teacher']);
    }

    public function test_study_plan_adaptive_revision_tracks_progress_and_mastery(): void
    {
        Notification::fake();
        $context = $this->context('adaptive');
        $learnerUser = User::factory()->create();
        $context['learner']->update(['user_id' => $learnerUser->id]);
        [$attempt, $answer] = $this->writtenSubmission($context);
        $approved = app(QuizService::class)->review($attempt->load('assessment'), $context['teacher'], [
            $answer->uuid => ['marks_awarded' => 2, 'teacher_feedback' => 'Practise inverse operations.'],
        ]);
        $this->fakeAiSequence([$this->studyPlanResponse(), $this->retestResponse()]);
        app(QuizService::class)->release($approved->load('assessment'), $context['teacher']);
        $plan = QuizStudyPlan::query()->where('quiz_attempt_id', $attempt->id)->firstOrFail();

        $progress = app(StudyPlanService::class)->recordProgress($plan, $context['learner']->refresh(), ['day-1', 'easy-1'], 35);
        $this->assertSame(50, $progress->completion_percentage);
        $this->assertSame(35, $progress->time_spent_minutes);

        $revision = app(StudyPlanService::class)->retest($progress, $context['learner']->refresh(), ['easy-1' => 'Both sides must stay balanced.', 'medium-1' => 'Subtract three.', 'challenge-1' => 'Use the inverse operation.']);
        $this->assertSame('evaluated', $revision->status);
        $this->assertSame('85.00', $revision->score_percentage);
        $this->assertSame(['Explain equality.'], $progress->refresh()->mastered_concepts);
        $this->assertSame([], $progress->remaining_concepts);
        $this->assertDatabaseHas('ai_grading_requests', ['request_type' => 'adaptive_retest', 'status' => 'completed']);
    }

    public function test_learner_dashboard_and_teacher_study_plan_analytics_use_published_progress(): void
    {
        Notification::fake();
        $context = $this->context('learner-dashboard');
        $learnerUser = User::factory()->create();
        $learnerRole = Role::query()->where('name', 'Learner')->firstOrFail();
        Membership::query()->create(['organization_id' => $context['organization']->id, 'user_id' => $learnerUser->id, 'role_id' => $learnerRole->id, 'status' => 'active', 'is_default' => true]);
        $context['learner']->update(['user_id' => $learnerUser->id, 'portal_access_enabled' => true]);
        [$attempt, $answer] = $this->writtenSubmission($context);
        $approved = app(QuizService::class)->review($attempt->load('assessment'), $context['teacher'], [
            $answer->uuid => ['marks_awarded' => 2, 'teacher_feedback' => 'Revise equality.'],
        ]);
        $this->fakeAi($this->studyPlanResponse());
        app(QuizService::class)->release($approved->load('assessment'), $context['teacher']);

        $this->actingAs($learnerUser)->withSession(['organization_id' => $context['organization']->id])
            ->get(route('quizzes.assigned'))
            ->assertOk()
            ->assertSee('Adaptive learning dashboard')
            ->assertSee('Explain equality.')
            ->assertDontSee('AI confidence');

        $analytics = app(StudyPlanService::class)->analytics($context['organization']->id);
        $this->assertSame(0.0, $analytics['average_completion']);
        $this->assertSame(1, $analytics['most_missed_concepts']['Explain equality.']);
        $this->assertCount(1, $analytics['students_needing_intervention']);
    }

    public function test_invalid_ai_criteria_total_is_rejected_without_overwriting_teacher_marks(): void
    {
        $context = $this->context('invalid-ai');
        [$attempt, $answer] = $this->writtenSubmission($context);
        config(['ai.default_provider' => 'openai', 'ai.fallback_provider' => null, 'ai.providers.openai.enabled' => true, 'ai.providers.openai.api_key' => 'test-only', 'ai.providers.openai.base_url' => 'https://api.openai.test/v1']);
        $payload = $this->aiResponse(4);
        $payload['output_text'] = json_encode([...json_decode($payload['output_text'], true), 'criteria' => [['criterion' => 'Balance', 'met' => true, 'marks_awarded' => 3]]]);
        Http::fake(['https://api.openai.test/v1/responses' => Http::response($payload)]);

        try {
            app(QuizService::class)->suggestWrittenMark($answer, $context['teacher']);
            $this->fail('Mismatched criteria total was accepted.');
        } catch (DomainException) {
        }

        $this->assertNull($answer->refresh()->ai_suggested_mark);
        $this->assertNull($answer->marks_awarded);
        $this->assertSame('submitted', $attempt->refresh()->status);
    }

    private function quiz(array $context): Assessment
    {
        return app(AssessmentService::class)->create($context['organization'], $context['teacher'], ['academic_year_id' => $context['year']->id, 'academic_term_id' => $context['term']->id, 'grade_id' => $context['grade']->id, 'class_id' => $context['class']->id, 'subject_id' => $context['subject']->id, 'assessment_category_id' => $context['category']->id, 'title' => 'Quiz '.$context['organization']->code, 'maximum_mark' => 1]);
    }

    private function writtenSubmission(array $context): array
    {
        $quiz = $this->quiz($context);
        $question = app(QuizService::class)->addQuestion($quiz, $context['teacher'], ['type' => 'short_response', 'prompt' => 'Explain equality.', 'marks_available' => 5, 'model_answer' => 'Both sides remain equal.', 'marking_guidance' => 'Award for balance and inverse operation.']);
        app(QuizService::class)->publish($quiz->refresh(), $context['teacher']);
        $attempt = app(QuizService::class)->start($quiz->refresh(), $context['learner']);
        $attempt = app(QuizService::class)->submit($attempt, $context['learner'], [$question->uuid => ['answer_text' => 'Do the same thing on both sides.']]);

        return [$attempt, $attempt->answers->first()];
    }

    private function aiResponse(float $mark): array
    {
        return [
            'output_text' => json_encode([
                'awarded_marks' => $mark,
                'max_marks' => 5,
                'criteria' => [['criterion' => 'Balance', 'met' => true, 'marks_awarded' => $mark]],
                'strengths' => ['Understands balance'],
                'improvements' => ['Name the inverse operation'],
                'misconceptions' => [],
                'grading_rationale' => 'Correct core idea with one missing detail.',
                'confidence' => 0.8,
                'requires_teacher_review' => true,
            ]),
            'usage' => ['input_tokens' => 100, 'output_tokens' => 60],
        ];
    }

    private function studyPlanResponse(string $summary = 'Build equality mastery with targeted practice.'): array
    {
        return [
            'summary' => $summary,
            'weak_concepts' => ['Explain equality.'],
            'learning_goals' => ['Explain why both sides remain balanced.'],
            'daily_schedule' => [['activity_id' => 'day-1', 'day' => 1, 'duration_minutes' => 30, 'topic' => 'Explain equality.', 'activity' => 'Review a worked equality example.']],
            'revision_exercises' => [
                ['activity_id' => 'easy-1', 'concept' => 'Explain equality.', 'difficulty' => 'easy', 'question' => 'What keeps an equation balanced?', 'success_criteria' => 'State that the same operation applies to both sides.'],
                ['activity_id' => 'medium-1', 'concept' => 'Explain equality.', 'difficulty' => 'medium', 'question' => 'Solve x + 3 = 7 and explain each step.', 'success_criteria' => 'Use an inverse operation on both sides.'],
                ['activity_id' => 'challenge-1', 'concept' => 'Explain equality.', 'difficulty' => 'challenge', 'question' => 'Explain why subtracting different values breaks equality.', 'success_criteria' => 'Connect equal operations to balance.'],
            ],
            'reflection_questions' => ['Which operation preserves equality?'],
            'recommended_videos' => [['title' => 'Balancing equations', 'search_topic' => 'equation balance inverse operations']],
            'recommended_reading' => [['title' => 'Equality worked examples', 'description' => 'Review balanced equation steps.']],
            'estimated_duration_minutes' => 120,
            'success_criteria' => ['Complete all three difficulty levels.'],
            'next_assessment_recommendation' => 'Retest equality after completing the challenge exercise.',
            'teacher_comment' => 'Show the same operation on both sides.',
        ];
    }

    private function retestResponse(): array
    {
        return ['score_percentage' => 85, 'mastered_concepts' => ['Explain equality.'], 'feedback' => 'Equality is now explained accurately.', 'ready_for_next_assessment' => true];
    }

    private function fakeAi(array $content): void
    {
        config(['ai.default_provider' => 'openai', 'ai.fallback_provider' => null, 'ai.providers.openai.enabled' => true, 'ai.providers.openai.api_key' => 'test-only', 'ai.providers.openai.base_url' => 'https://api.openai.test/v1']);
        Http::fake(['https://api.openai.test/v1/responses' => Http::response(['output_text' => json_encode($content), 'usage' => ['input_tokens' => 120, 'output_tokens' => 180]], 200)]);
    }

    private function fakeAiSequence(array $contents): void
    {
        config(['ai.default_provider' => 'openai', 'ai.fallback_provider' => null, 'ai.providers.openai.enabled' => true, 'ai.providers.openai.api_key' => 'test-only', 'ai.providers.openai.base_url' => 'https://api.openai.test/v1']);
        $sequence = Http::sequence();
        foreach ($contents as $content) {
            $sequence->push(['output_text' => json_encode($content), 'usage' => ['input_tokens' => 120, 'output_tokens' => 180]]);
        }
        Http::fake(['https://api.openai.test/v1/responses' => $sequence]);
    }

    private function context(string $code): array
    {
        $this->seed(AssessmentsPermissionSeeder::class);
        $organization = Organization::query()->create(['name' => $code, 'code' => $code, 'type' => 'school']);
        foreach (['assessments', 'academics', 'learners', 'staff'] as $module) {
            OrganizationModule::query()->create(['organization_id' => $organization->id, 'module_name' => $module, 'enabled' => true]);
        }
        $teacher = User::factory()->create();
        $role = Role::query()->where('name', 'Teacher')->firstOrFail();
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $teacher->id, 'role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
        $year = AcademicYear::query()->create(['organization_id' => $organization->id, 'name' => '2026 '.$code, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $term = AcademicTerm::query()->create(['organization_id' => $organization->id, 'academic_year_id' => $year->id, 'term_number' => 1, 'name' => 'Term 1', 'start_date' => '2026-01-01', 'end_date' => '2026-03-31']);
        $grade = Grade::query()->create(['organization_id' => $organization->id, 'academic_year_id' => $year->id, 'name' => 'Grade '.$code, 'order' => 1]);
        $class = ClassGroup::query()->create(['organization_id' => $organization->id, 'academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => 'Class '.$code]);
        $subject = Subject::query()->create(['organization_id' => $organization->id, 'name' => 'Mathematics '.$code, 'code' => 'M'.$code]);
        $category = app(AssessmentCategoryService::class)->create($organization, $teacher, ['name' => 'Quiz', 'code' => 'QUIZ']);
        $learner = LearnerProfile::factory()->create(['organization_id' => $organization->id, 'current_academic_year_id' => $year->id, 'current_grade_id' => $grade->id, 'current_class_id' => $class->id, 'learner_status' => 'active']);

        return compact('organization', 'teacher', 'year', 'term', 'grade', 'class', 'subject', 'category', 'learner');
    }
}
