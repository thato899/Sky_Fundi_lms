<?php

declare(strict_types=1);

namespace Core\Notifications\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Notifications\Application\NotificationService;
use Core\Notifications\Http\Requests\UpdatePreferenceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A user manages only their own notification preferences — there is no
 * "manage" permission gate here, only authentication, since this is
 * self-service by design.
 */
final class NotificationPreferenceController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->ok(['data' => $this->notifications->preferencesFor($request->user())]);
    }

    public function update(UpdatePreferenceRequest $request): JsonResponse
    {
        $preference = $this->notifications->setPreference(
            user: $request->user(),
            type: $request->string('type')->value(),
            channel: $request->string('channel')->value(),
            enabled: $request->boolean('enabled'),
        );

        return $this->ok(['data' => $preference]);
    }
}
