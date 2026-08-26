<?php

declare(strict_types=1);

namespace Modules\Materials\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Materials\Domain\Enums\MaterialSourceType;
use Modules\Materials\Domain\Enums\MaterialStatus;
use Modules\Organizations\Infrastructure\Models\Organization;

/**
 * Teacher/tutor-uploaded course material — see modules/Materials/README.md
 * and docs/adr/011-materials-retrieval.md. Ingestion is text-only in
 * this pass; `content` holds the already-extracted text.
 *
 * @property string $id
 * @property string $uuid
 * @property string $organization_id
 * @property string|null $subject_id
 * @property string $uploaded_by
 * @property string $title
 * @property string $content
 * @property MaterialStatus $status
 * @property string|null $failure_message
 * @property Subject|null $subject
 * @property Carbon $created_at
 */
final class Material extends Model
{
    use HasUuidPrimaryKey;
    use SoftDeletes;

    protected $fillable = ['uuid', 'organization_id', 'subject_id', 'uploaded_by', 'title', 'source_type', 'content', 'status', 'failure_message'];

    public function uniqueIds(): array
    {
        return ['id', 'uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'source_type' => MaterialSourceType::class,
            'status' => MaterialStatus::class,
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(MaterialChunk::class);
    }
}
