<?php

declare(strict_types=1);

namespace Modules\Materials\Application;

use Core\AIGateway\Application\AIManager;
use Core\AIGateway\Application\DTOs\AIRequest;
use Core\AuditLogs\Application\AuditLogService;
use Core\Support\Exceptions\DomainException;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;

/**
 * The learner-facing AI tutor chat — see docs/adr/011-materials-retrieval.md.
 * Always resolves the organization from the learner's own record,
 * never from client input, and MaterialRetrievalService always filters
 * by that organization_id at the query level — so a learner can never
 * retrieve, and therefore never be answered from, another
 * organization's material. See TutorChatIsolationTest.
 */
final class TutorChatService
{
    public function __construct(
        private readonly MaterialRetrievalService $retrieval,
        private readonly AIManager $ai,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * @return array{answer: string, citations: list<array{material_title: string, chunk_index: int}>, grounded: bool}
     */
    public function ask(LearnerProfile $learner, string $question): array
    {
        $question = trim($question);
        if ($question === '') {
            throw new DomainException('Ask a question to start.');
        }

        /** @var Organization $organization */
        $organization = $learner->organization()->firstOrFail();
        $matches = $this->retrieval->topK($organization, $question);

        if ($matches->isEmpty()) {
            $result = [
                'answer' => "I don't have any course material to answer that from yet — ask your teacher to upload some, or try a different question once they have.",
                'citations' => [],
                'grounded' => false,
            ];
            $this->audit->record('materials.tutor_chat_answered', $learner, after: ['organization_id' => $organization->getKey(), 'grounded' => false]);

            return $result;
        }

        $context = $matches
            ->map(fn (array $match, int $index): string => '['.($index + 1).'] '.$match['chunk']->content)
            ->implode("\n\n");

        $response = $this->ai->complete(new AIRequest(
            prompt: "Course material excerpts:\n{$context}\n\nLearner question: {$question}",
            capability: 'materials.tutor_chat',
            tenantId: $organization->getKey(),
            moduleId: 'materials',
            preferredProvider: (string) config('ai.embedding_provider'),
            temperature: 0.3,
            maxTokens: 600,
            metadata: [
                'instructions' => 'You are a patient tutor helping a learner understand their own course material. Answer ONLY using the numbered excerpts provided — if the excerpts do not contain the answer, say so plainly rather than guessing. Reference excerpt numbers like [1] where you use them. Keep the answer concise and age-appropriate. Never invent facts not present in the excerpts.',
            ],
        ));

        $citations = $matches->map(fn (array $match): array => [
            'material_title' => $match['chunk']->material->title,
            'chunk_index' => $match['chunk']->chunk_index,
        ])->values()->all();

        $result = ['answer' => $response->content, 'citations' => $citations, 'grounded' => true];
        $this->audit->record('materials.tutor_chat_answered', $learner, after: ['organization_id' => $organization->getKey(), 'grounded' => true, 'citation_count' => count($citations)]);

        return $result;
    }
}
