<?php

declare(strict_types=1);

namespace Core\Security\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Security\Application\SessionSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SessionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly SessionSecurityService $sessions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->ok(['data' => $this->sessions->activeSessionsFor($request->user())]);
    }

    public function destroy(Request $request, int $token): JsonResponse
    {
        $this->sessions->revoke($request->user(), $token);

        return $this->noContent();
    }

    public function destroyOthers(Request $request): JsonResponse
    {
        $currentTokenId = $request->user()->currentAccessToken()?->id;
        $revoked = $this->sessions->revokeAllExcept($request->user(), $currentTokenId);

        return $this->ok(['message' => "{$revoked} other session(s) revoked."]);
    }
}
