<?php

declare(strict_types=1);

namespace Modules\Materials\Application;

use Core\AIGateway\Application\AIManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Materials\Infrastructure\Models\MaterialChunk;
use Modules\Organizations\Infrastructure\Models\Organization;

/**
 * Brute-force cosine similarity over an organization's chunk
 * embeddings — see docs/adr/011-materials-retrieval.md for why this,
 * not a vector database, is the deliberate design. The organization
 * filter is always applied at the query level (never as an
 * after-the-fact in-memory filter), so a cross-organization chunk is
 * never loaded for a request — see TutorChatIsolationTest.
 */
final class MaterialRetrievalService
{
    private const DEFAULT_TOP_K = 5;

    public function __construct(private readonly AIManager $ai) {}

    /**
     * @return Collection<int, array{chunk: MaterialChunk, score: float}>
     */
    public function topK(Organization $organization, string $query, int $k = self::DEFAULT_TOP_K, ?string $subjectId = null): Collection
    {
        $queryVector = $this->ai->embed($query, (string) config('ai.embedding_provider'));

        /** @var Builder<MaterialChunk> $chunksQuery */
        $chunksQuery = MaterialChunk::query()
            ->with('material')
            ->where('organization_id', $organization->getKey())
            ->whereNotNull('embedding');
        if ($subjectId !== null) {
            $chunksQuery->whereHas('material', fn ($materialQuery) => $materialQuery->where('subject_id', $subjectId));
        }
        $chunks = $chunksQuery->get();

        return $chunks
            ->map(fn (MaterialChunk $chunk): array => ['chunk' => $chunk, 'score' => $this->cosineSimilarity($queryVector, $chunk->embedding ?? [])])
            ->sortByDesc('score')
            ->take($k)
            ->values();
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        if ($a === [] || $b === [] || count($a) !== count($b)) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        foreach ($a as $i => $value) {
            $dot += $value * $b[$i];
            $normA += $value ** 2;
            $normB += $b[$i] ** 2;
        }
        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
