<?php

declare(strict_types=1);

namespace Modules\Assessments\Tests\Feature;

use Core\Identity\Infrastructure\Models\Membership;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Academics\Infrastructure\Models\AcademicTerm;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Assessments\Application\AssessmentCategoryService;
use Modules\Assessments\Application\AssessmentService;
use Modules\Assessments\Application\QuizDraftService;
use Modules\Assessments\Database\Seeders\AssessmentsPermissionSeeder;
use Modules\Assessments\Infrastructure\Models\Assessment;
use Modules\Materials\Application\MaterialIngestionService;
use Modules\Materials\Database\Seeders\MaterialsPermissionSeeder;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Infrastructure\Models\OrganizationModule;
use Tests\TestCase;

/**
 * Proves docs/adr/011-materials-retrieval.md's "never bypasses the
 * existing review gate": every AI-drafted question lands as an
 * ordinary Draft-status question a teacher can edit/remove, and the
 * assessment still requires an explicit publish() before a learner
 * can see it.
 */
final class QuizDraftServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_generation_inserts_ordinary_draft_questions_that_still_require_publish(): void
    {
        Http::fake([
            'https://api.gemini.test/models/text-embedding-test:embedContent' => Http::response(['embedding' => ['values' => [1.0, 0.0]]]),
            'https://api.gemini.test/models/gemini-test:generateContent' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode($this->draftResponse(), JSON_THROW_ON_ERROR)]]]]],
            ]),
        ]);
        $context = $this->context('draft-happy');
        $quiz = $this->quiz($context);
        app(MaterialIngestionService::class)->ingest($context['organization'], $context['teacher'], [
            'title' => 'Equality Notes',
            'content' => str_repeat('Both sides of an equation must remain balanced. ', 6),
            'subject_id' => $context['subject']->id,
        ]);

        $result = app(QuizDraftService::class)->generateDraft($quiz, $context['teacher'], 'Equality', 2);

        $this->assertSame(2, $result['questions_added']);
        $this->assertSame(0, $result['questions_skipped']);
        $quiz = $quiz->refresh()->load('questions.options');
        $this->assertCount(2, $quiz->questions);
        // The gate: AI drafting alone never publishes — the assessment
        // stays Draft (not visible to any learner) until a teacher
        // explicitly calls QuizService::publish(), exactly as if the
        // questions had been typed by hand.
        $this->assertSame('draft', $quiz->status->value);
    }

    public function test_draft_result_reports_the_source_excerpt_count_used_for_grounding(): void
    {
        Http::fake([
            'https://api.gemini.test/models/text-embedding-test:embedContent' => Http::response(['embedding' => ['values' => [1.0, 0.0]]]),
            'https://api.gemini.test/models/gemini-test:generateContent' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode($this->draftResponse(), JSON_THROW_ON_ERROR)]]]]],
            ]),
        ]);
        $context = $this->context('draft-excerpts');
        $quiz = $this->quiz($context);
        app(MaterialIngestionService::class)->ingest($context['organization'], $context['teacher'], [
            'title' => 'Equality Notes',
            'content' => str_repeat('Both sides of an equation must remain balanced. ', 6),
            'subject_id' => $context['subject']->id,
        ]);

        $result = app(QuizDraftService::class)->generateDraft($quiz, $context['teacher'], 'Equality', 2);

        $this->assertGreaterThan(0, $result['source_excerpt_count']);
    }

    public function test_difficulty_is_sent_to_the_provider_and_true_false_questions_are_accepted(): void
    {
        $trueFalseDraft = [
            'questions' => [
                ['type' => 'true_false', 'prompt' => 'Equality is preserved when the same operation is applied to both sides.', 'marks_available' => 1, 'options' => [['label' => 'True', 'is_correct' => true], ['label' => 'False', 'is_correct' => false]], 'model_answer' => '', 'marking_guidance' => '', 'key_concepts' => []],
            ],
        ];
        Http::fake([
            'https://api.gemini.test/models/text-embedding-test:embedContent' => Http::response(['embedding' => ['values' => [1.0, 0.0]]]),
            'https://api.gemini.test/models/gemini-test:generateContent' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode($trueFalseDraft, JSON_THROW_ON_ERROR)]]]]],
            ]),
        ]);
        $context = $this->context('draft-difficulty');
        $quiz = $this->quiz($context);
        app(MaterialIngestionService::class)->ingest($context['organization'], $context['teacher'], [
            'title' => 'Equality Notes',
            'content' => str_repeat('Both sides of an equation must remain balanced. ', 6),
            'subject_id' => $context['subject']->id,
        ]);

        $result = app(QuizDraftService::class)->generateDraft($quiz, $context['teacher'], 'Equality', 1, 'hard');

        $this->assertSame(1, $result['questions_added']);
        $quiz = $quiz->refresh()->load('questions.options');
        $this->assertSame('true_false', $quiz->questions->first()->type->value);
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'generateContent')) {
                return true; // not the completion call — ignore
            }

            return str_contains($request->body(), 'analysis/reasoning');
        });
    }

    public function test_an_invalid_difficulty_is_rejected(): void
    {
        $context = $this->context('draft-bad-difficulty');
        $quiz = $this->quiz($context);

        $this->expectException(DomainException::class);
        app(QuizDraftService::class)->generateDraft($quiz, $context['teacher'], 'Equality', 5, 'impossible');
    }

    public function test_draft_generation_without_ingested_material_is_rejected(): void
    {
        Http::fake(['https://api.gemini.test/models/text-embedding-test:embedContent' => Http::response(['embedding' => ['values' => [1.0, 0.0]]])]);
        $context = $this->context('draft-no-material');
        $quiz = $this->quiz($context);

        $this->expectException(DomainException::class);
        app(QuizDraftService::class)->generateDraft($quiz, $context['teacher'], 'Anything');
    }

    public function test_a_malformed_draft_question_is_skipped_not_fatal(): void
    {
        $goodAndBad = [
            'questions' => [
                ['type' => 'multiple_choice', 'prompt' => 'Valid?', 'marks_available' => 1, 'options' => [['label' => 'Yes', 'is_correct' => true], ['label' => 'No', 'is_correct' => false]], 'model_answer' => '', 'marking_guidance' => '', 'key_concepts' => []],
                ['type' => 'not_a_real_type', 'prompt' => 'Broken', 'marks_available' => 1, 'options' => [], 'model_answer' => '', 'marking_guidance' => '', 'key_concepts' => []],
            ],
        ];
        Http::fake([
            'https://api.gemini.test/models/text-embedding-test:embedContent' => Http::response(['embedding' => ['values' => [1.0, 0.0]]]),
            'https://api.gemini.test/models/gemini-test:generateContent' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode($goodAndBad, JSON_THROW_ON_ERROR)]]]]],
            ]),
        ]);
        $context = $this->context('draft-partial');
        $quiz = $this->quiz($context);
        app(MaterialIngestionService::class)->ingest($context['organization'], $context['teacher'], [
            'title' => 'Notes',
            'content' => str_repeat('Some real material content here. ', 6),
            'subject_id' => $context['subject']->id,
        ]);

        $result = app(QuizDraftService::class)->generateDraft($quiz, $context['teacher'], 'Topic');

        $this->assertSame(1, $result['questions_added']);
        $this->assertSame(1, $result['questions_skipped']);
    }

    private function draftResponse(): array
    {
        return [
            'questions' => [
                ['type' => 'multiple_choice', 'prompt' => 'What must remain balanced?', 'marks_available' => 1, 'options' => [['label' => 'Both sides', 'is_correct' => true], ['label' => 'Neither side', 'is_correct' => false]], 'model_answer' => '', 'marking_guidance' => '', 'key_concepts' => ['equality']],
                ['type' => 'short_response', 'prompt' => 'Explain why equality is preserved.', 'marks_available' => 3, 'options' => [], 'model_answer' => 'Applying the same operation to both sides keeps them equal.', 'marking_guidance' => 'Award for stating the operation is applied to both sides.', 'key_concepts' => ['equality', 'inverse operations']],
            ],
        ];
    }

    private function quiz(array $context): Assessment
    {
        return app(AssessmentService::class)->create($context['organization'], $context['teacher'], ['academic_year_id' => $context['year']->id, 'academic_term_id' => $context['term']->id, 'grade_id' => $context['grade']->id, 'class_id' => $context['class']->id, 'subject_id' => $context['subject']->id, 'assessment_category_id' => $context['category']->id, 'title' => 'Quiz '.$context['organization']->code, 'maximum_mark' => 1]);
    }

    private function context(string $code): array
    {
        // generateDraft() is a completion call and must resolve against
        // ai.default_provider (see QuizDraftService) — explicit here so a
        // regression back to ai.embedding_provider would be caught even
        // though both happen to be "gemini" by coincidence in this suite's
        // base config; the model names differ per env var below, so a
        // request routed to the wrong one hits the wrong mocked URL and
        // fails loudly instead of silently.
        config(['ai.default_provider' => 'gemini']);
        $this->seed(AssessmentsPermissionSeeder::class);
        $this->seed(MaterialsPermissionSeeder::class);
        $organization = Organization::query()->create(['name' => $code, 'code' => $code, 'type' => 'school']);
        foreach (['assessments', 'academics', 'learners', 'staff', 'materials'] as $module) {
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

        return compact('organization', 'teacher', 'year', 'term', 'grade', 'class', 'subject', 'category');
    }
}
