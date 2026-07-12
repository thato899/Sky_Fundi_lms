<?php

declare(strict_types=1);

namespace Core\Security\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Security\Application\TrustedDeviceService;
use Core\Security\Http\Resources\TrustedDeviceResource;
use Core\Security\Infrastructure\Models\TrustedDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Self-service — a user manages only their own trusted devices, no
 * permission gate beyond authentication.
 */
final class TrustedDeviceController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly TrustedDeviceService $devices,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->ok(TrustedDeviceResource::collection($this->devices->listFor($request->user())));
    }

    public function store(Request $request): JsonResponse
    {
        $device = $this->devices->trust(
            user: $request->user(),
            ipAddress: $request->ip() ?? '0.0.0.0',
            userAgent: $request->userAgent() ?? 'unknown',
            deviceName: $request->string('device_name')->value() ?: null,
        );

        return $this->created(new TrustedDeviceResource($device));
    }

    public function destroy(Request $request, TrustedDevice $trustedDevice): JsonResponse
    {
        abort_unless($trustedDevice->user_id === $request->user()->id, 403);

        $this->devices->revoke($trustedDevice);

        return $this->noContent();
    }
}
