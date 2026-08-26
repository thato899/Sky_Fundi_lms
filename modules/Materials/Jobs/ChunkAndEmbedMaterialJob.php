<?php

declare(strict_types=1);

namespace Modules\Materials\Jobs;

use Core\AIGateway\Application\AIManager;
use Core\Queue\Domain\QueueName;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Materials\Domain\Enums\MaterialStatus;
use Modules\Materials\Infrastructure\Models\Material;
use Modules\Materials\Infrastructure\Models\MaterialChunk;

/**
 * The async chunking job named in the brief — splits a Material's text
 * into chunks and embeds each one via Core\AIGateway\Application\AIManager::embed(),
 * see docs/adr/011-materials-retrieval.md. Takes the Material's id, not
 * the model, so a stale in-memory copy is never serialized onto the
 * queue.
 */
final class ChunkAndEmbedMaterialJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const CHUNK_TARGET_LENGTH = 800;

    public int $tries = 3;

    public function __construct(private readonly string $materialId)
    {
        $this->onQueue(QueueName::Ai->value);
    }

    public function handle(AIManager $ai): void
    {
        $material = Material::query()->find($this->materialId);
        if ($material === null) {
            return;
        }

        $material->update(['status' => MaterialStatus::Chunking, 'failure_message' => null]);

        try {
            $chunks = $this->chunk($material->content);
            if ($chunks === []) {
                throw new \RuntimeException('No chunkable content was found.');
            }

            $embeddingProvider = (string) config('ai.embedding_provider');
            foreach ($chunks as $index => $text) {
                $embedding = $ai->embed($text, $embeddingProvider);
                MaterialChunk::query()->updateOrCreate(
                    ['material_id' => $material->getKey(), 'chunk_index' => $index],
                    ['organization_id' => $material->organization_id, 'content' => $text, 'embedding' => $embedding, 'token_count' => (int) ceil(mb_strlen($text) / 4)],
                );
            }

            $material->update(['status' => MaterialStatus::Ready]);
        } catch (\Throwable $exception) {
            $material->update(['status' => MaterialStatus::Failed, 'failure_message' => 'Chunking or embedding failed; the material was not made available for retrieval.']);
            report($exception);
        }
    }

    /**
     * Paragraph-aware, target-length chunking: accumulates whole
     * paragraphs until adding the next would exceed the target length,
     * then starts a new chunk — never splits mid-sentence where a
     * paragraph boundary exists to split on instead.
     *
     * @return list<string>
     */
    private function chunk(string $content): array
    {
        $paragraphs = array_values(array_filter(array_map('trim', preg_split('/\R{2,}/', $content) ?: [])));
        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            if ($current !== '' && mb_strlen($current) + mb_strlen($paragraph) + 2 > self::CHUNK_TARGET_LENGTH) {
                $chunks[] = $current;
                $current = '';
            }
            $current = $current === '' ? $paragraph : $current."\n\n".$paragraph;

            while (mb_strlen($current) > self::CHUNK_TARGET_LENGTH * 2) {
                $chunks[] = mb_substr($current, 0, self::CHUNK_TARGET_LENGTH);
                $current = mb_substr($current, self::CHUNK_TARGET_LENGTH);
            }
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }
}
