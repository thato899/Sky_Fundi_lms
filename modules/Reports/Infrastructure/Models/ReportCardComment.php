<?php

declare(strict_types=1);

namespace Modules\Reports\Infrastructure\Models;

use Core\Support\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReportCardComment extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['uuid', 'organization_id', 'report_card_id', 'comment_type', 'comment', 'author_user_id', 'staff_profile_id'];

    public function uniqueIds(): array
    {
        return ['id', 'uuid'];
    }

    public function reportCard(): BelongsTo
    {
        return $this->belongsTo(ReportCard::class);
    }
}
