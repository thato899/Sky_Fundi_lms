<?php

declare(strict_types=1);

namespace Modules\Assessments\Application;

use Core\AIGateway\Application\AIManager;
use Core\AIGateway\Application\DTOs\AIRequest;
use Core\AuditLogs\Application\AuditLogService;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Modules\Assessments\Domain\Enums\QuestionType;
use Modules\Assessments\Infrastructure\Models\Assessment;
use Modules\Materials\Application\MaterialRetrievalService;
use Modules\Organizations\Infrastructure\Models\Organization;

/**
 * Retrieval-grounded draft question generation — see
 * docs/adr/011-materials-retrieval.md ("AI-drafted quiz generation
 * never bypasses the existing review gate"). Every draft question is
 * inserted through QuizService::addQuestion(), the exact path a
 * teacher's own manually-authored questions use — it already enforces
 * the assessment being Draft-status and teacher-owned, and the
 * assessment still requires an explicit QuizService::publish() call
 * before any learner can see it. This service adds no new state and
 * no new authorization path; it is purely a bulk, AI-assisted way to
 * populate ordinary Draft questions a teacher can edit or delete
 * exactly like hand-authored ones before publishing.
 */
final class QuizDraftService
{
    private const DEFAULT_QUESTION_COUNT = 5;

    public function __construct(
        private readonly AIManager $ai,
        private readonly MaterialRetrievalService $retrieval,
        private readonly QuizService $quizzes,
        private readonly AuditLogService $audit,
    ) {}

    private const DIFFICULTIES = ['easy', 'medium', 'hard'];

    /**
     * @return array{questions_added: int, questions_skipped: int, source_excerpt_count: int}
     */
    public function generateDraft(Assessment $assessment, User $teacher, string $topic, int $questionCount = self::DEFAULT_QUESTION_COUNT, ?string $difficulty = null): array
    {
        if ($difficulty !== null && ! in_array($difficulty, self::DIFFICULTIES, true)) {
            throw new DomainException('Difficulty must be easy, medium, or hard.');
        }

        $organization = Organization::query()->findOrFail($assessment->organization_id);
        $matches = $this->retrieval->topK($organization, $topic, k: 8, subjectId: $assessment->subject_id);
        if ($matches->isEmpty()) {
            throw new DomainException('No ingested course material was found to draft questions from — upload material first.');
        }

        $context = $matches
            ->map(fn (array $match, int $index): string => '['.($index + 1).'] '.$match['chunk']->content)
            ->implode("\n\n");

        $response = $this->ai->complete(new AIRequest(
            prompt: "Course material excerpts:\n{$context}\n\nDraft quiz questions about: {$topic}",
            capability: 'materials.quiz_draft',
            tenantId: $assessment->organization_id,
            moduleId: 'assessments',
            // The completion provider, same as every other AI call in this
            // module — not ai.embedding_provider, which AIManager::embed()
            // alone resolves against (see config/ai.php). Using the
            // embedding provider here previously only worked by
            // coincidence, since this deployment happens to use Gemini for
            // both; it would silently break the moment the two config
            // values diverged.
            preferredProvider: config('ai.default_provider'),
            temperature: 0.4,
            maxTokens: 2000,
            metadata: [
                'instructions' => "Draft exactly {$questionCount} quiz questions grounded ONLY in the supplied excerpts — never invent facts not present in them. Mix multiple_choice (4 options, exactly one correct), true_false, and short_response (with a model answer and marking guidance) types.".$this->difficultyInstruction($difficulty),
                'schema_name' => 'quiz_draft',
                'json_schema' => $this->draftSchema(),
            ],
        ));

        $draft = json_decode($response->content, true, flags: JSON_THROW_ON_ERROR);
        $added = 0;
        $skipped = 0;
        foreach ((array) ($draft['questions'] ?? []) as $questionData) {
            try {
                $this->quizzes->addQuestion($assessment, $teacher, $this->normalizeQuestion($questionData));
                $added++;
            } catch (\Throwable $exception) {
                report($exception);
                $skipped++;
            }
        }

        $this->audit->record('quizzes.ai_draft_generated', $assessment, after: ['organization_id' => $assessment->organization_id, 'questions_added' => $added, 'questions_skipped' => $skipped, 'topic' => $topic, 'difficulty' => $difficulty, 'source_excerpt_count' => $matches->count()]);

        return ['questions_added' => $added, 'questions_skipped' => $skipped, 'source_excerpt_count' => $matches->count()];
    }

    private function difficultyInstruction(?string $difficulty): string
    {
        return match ($difficulty) {
            'easy' => ' Keep questions at an easy, recall/definition level — testing whether the learner remembers the core facts.',
            'medium' => ' Write questions at a medium, application level — the learner must apply a concept to a slightly new situation, not just recall it.',
            'hard' => ' Write challenging questions at an analysis/reasoning level — require the learner to compare, explain a cause-and-effect relationship, or justify a conclusion using the material.',
            default => '',
        };
    }

    private function normalizeQuestion(mixed $questionData): array
    {
        if (! is_array($questionData)) {
            throw new DomainException('AI draft output contained a malformed question.');
        }
        $type = QuestionType::from((string) ($questionData['type'] ?? ''));

        return [
            'type' => $type->value,
            'prompt' => trim((string) ($questionData['prompt'] ?? '')),
            'marks_available' => $questionData['marks_available'] ?? 1,
            'options' => $type->isObjective() ? array_map(
                fn (mixed $option): array => ['label' => trim((string) ($option['label'] ?? '')), 'is_correct' => (bool) ($option['is_correct'] ?? false)],
                (array) ($questionData['options'] ?? []),
            ) : [],
            'model_answer' => $questionData['model_answer'] ?? null,
            'marking_guidance' => $questionData['marking_guidance'] ?? null,
            'key_concepts' => (array) ($questionData['key_concepts'] ?? []),
        ];
    }

    private function draftSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['questions'],
            'properties' => [
                'questions' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['type', 'prompt', 'marks_available', 'options', 'model_answer', 'marking_guidance', 'key_concepts'],
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => ['multiple_choice', 'true_false', 'short_response']],
                            'prompt' => ['type' => 'string'],
                            'marks_available' => ['type' => 'number'],
                            'options' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['label', 'is_correct'], 'properties' => ['label' => ['type' => 'string'], 'is_correct' => ['type' => 'boolean']]]],
                            'model_answer' => ['type' => 'string'],
                            'marking_guidance' => ['type' => 'string'],
                            'key_concepts' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                    ],
                ],
            ],
        ];
    }
}
