<?php

declare(strict_types=1);

namespace Core\AuditLogs\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\AuditLogs\Application\AuditLogService;
use Core\AuditLogs\Http\Resources\AuditLogResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only by design — audit logs are never edited or deleted through
 * the API. See docs/security/README.md#audit-logs.
 */
final class AuditLogController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AuditLogService $auditLogs,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginated = $this->auditLogs
            ->search($request->only(['action', 'category', 'actor_id', 'target_type', 'target_id', 'from', 'to']))
            ->paginate((int) $request->integer('per_page', 25));

        return $this->ok(AuditLogResource::collection($paginated));
    }

    public function categories(): JsonResponse
    {
        return $this->ok(['data' => $this->auditLogs->categories()]);
    }
}
