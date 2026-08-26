<?php

declare(strict_types=1);

namespace Modules\Materials\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Materials\Application\TutorChatService;
use Modules\Materials\Http\Requests\TutorChatRequest;
use Modules\Organizations\Infrastructure\Models\Organization;

/**
 * The AI tutor chat's own surface, resolving the learner exactly like
 * Modules\Learners\Http\Controllers\Web\LearnerPortalController does —
 * by the authenticated user's own portal-enabled learner profile in
 * the current organization context, never by a client-supplied
 * learner or organization identifier. See TutorChatIsolationTest.
 */
final class TutorChatController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TutorChatService $chat) {}

    public function ask(TutorChatRequest $request): JsonResponse
    {
        $learner = $this->learner($request);
        $result = $this->chat->ask($learner, (string) $request->validated('question'));

        return $this->ok($result);
    }

    private function learner(Request $request): LearnerProfile
    {
        $organization = $request->attributes->get('organization');
        abort_unless($organization instanceof Organization && $request->user() !== null, 403);
        $learner = LearnerProfile::query()
            ->where('organization_id', $organization->getKey())
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->where('portal_access_enabled', true)
            ->first();
        abort_unless($learner instanceof LearnerProfile, 403);

        return $learner;
    }
}
