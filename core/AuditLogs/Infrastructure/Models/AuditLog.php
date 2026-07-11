<?php

declare(strict_types=1);

namespace Core\AuditLogs\Infrastructure\Models;

use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An immutable record of a security- or platform-sensitive action.
 * Written only through AuditLogService::record() — never updated or
 * deleted through the application layer. See docs/security/README.md.
 */
final class AuditLog extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'audit_logs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function target(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'target_type', 'target_id');
    }
}
