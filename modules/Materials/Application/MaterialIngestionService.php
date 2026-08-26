<?php

declare(strict_types=1);

namespace Modules\Materials\Application;

use Core\AuditLogs\Application\AuditLogService;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Materials\Domain\Enums\MaterialSourceType;
use Modules\Materials\Domain\Enums\MaterialStatus;
use Modules\Materials\Infrastructure\Models\Material;
use Modules\Materials\Jobs\ChunkAndEmbedMaterialJob;
use Modules\Organizations\Infrastructure\Models\Organization;

/**
 * Text-only ingestion — see docs/adr/011-materials-retrieval.md. A PDF
 * path is a documented follow-up, not implemented here.
 */
final class MaterialIngestionService
{
    private const MIN_CONTENT_LENGTH = 50;

    public function __construct(private readonly AuditLogService $audit) {}

    public function ingest(Organization $organization, User $actor, array $data): Material
    {
        $content = trim((string) $data['content']);
        if (mb_strlen($content) < self::MIN_CONTENT_LENGTH) {
            throw new DomainException('Material content is too short to be useful — provide at least '.self::MIN_CONTENT_LENGTH.' characters.');
        }

        return DB::transaction(function () use ($organization, $actor, $data, $content): Material {
            $material = Material::query()->create([
                'uuid' => (string) Str::uuid(),
                'organization_id' => $organization->getKey(),
                'subject_id' => $data['subject_id'] ?? null,
                'uploaded_by' => $actor->getKey(),
                'title' => trim((string) $data['title']),
                'source_type' => MaterialSourceType::Text,
                'content' => $content,
                'status' => MaterialStatus::Pending,
            ]);
            $this->audit->record('materials.ingested', $material, after: ['organization_id' => $organization->getKey(), 'title' => $material->title]);
            ChunkAndEmbedMaterialJob::dispatch($material->getKey());

            return $material;
        });
    }

    public function delete(Material $material): void
    {
        $material->delete();
        $this->audit->record('materials.deleted', $material, after: ['organization_id' => $material->organization_id]);
    }
}
