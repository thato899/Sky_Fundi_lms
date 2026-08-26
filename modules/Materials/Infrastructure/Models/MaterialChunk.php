<?php

declare(strict_types=1);

namespace Modules\Materials\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One chunk of a Material's text, with its embedding vector stored as
 * a JSON float array — see docs/adr/011-materials-retrieval.md for why
 * there is no dedicated vector column type in this stack. Written only
 * by Jobs\ChunkAndEmbedMaterialJob.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $material_id
 * @property int $chunk_index
 * @property string $content
 * @property list<float>|null $embedding
 * @property int|null $token_count
 * @property Material $material
 */
final class MaterialChunk extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['organization_id', 'material_id', 'chunk_index', 'content', 'embedding', 'token_count'];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
