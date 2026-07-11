<?php

declare(strict_types=1);

namespace Core\AuditLogs\Application;

use Core\AuditLogs\Infrastructure\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as RequestFacade;

/**
 * The single write path for the platform's audit trail. Every Core
 * service and future module records security- or state-sensitive
 * actions through this service rather than writing to the audit_logs
 * table directly — see docs/security/README.md.
 */
final class AuditLogService
{
    public function record(
        string $action,
        ?Model $target = null,
        ?array $before = null,
        ?array $after = null,
        ?string $actorEmail = null,
    ): AuditLog {
        $actor = Auth::user();

        return AuditLog::create([
            'actor_id' => $actor?->id,
            'actor_email' => $actorEmail ?? $actor?->email,
            'action' => $action,
            'target_type' => $target?->getMorphClass(),
            'target_id' => $target?->getKey(),
            'before' => $before,
            'after' => $after,
            'ip_address' => RequestFacade::ip(),
            'user_agent' => RequestFacade::userAgent(),
        ]);
    }

    /**
     * Search audit logs by action, actor, target, and date range — see
     * core/AuditLogs/README.md ("Audit logs should be searchable.").
     * Returns a query builder so the controller can paginate/sort per
     * docs/api/conventions.md without this service reimplementing that.
     */
    public function search(array $filters): Builder
    {
        return AuditLog::query()
            ->when($filters['action'] ?? null, fn ($q, $action) => $q->where('action', 'like', "%{$action}%"))
            ->when($filters['actor_id'] ?? null, fn ($q, $actorId) => $q->where('actor_id', $actorId))
            ->when($filters['target_type'] ?? null, fn ($q, $type) => $q->where('target_type', $type))
            ->when($filters['target_id'] ?? null, fn ($q, $id) => $q->where('target_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', $to))
            ->latest('created_at');
    }
}
