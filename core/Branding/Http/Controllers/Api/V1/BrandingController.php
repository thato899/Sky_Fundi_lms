<?php

declare(strict_types=1);

namespace Core\Branding\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Branding\Application\BrandingService;
use Core\Branding\Http\Requests\UpdateBrandingRequest;
use Illuminate\Http\JsonResponse;

/**
 * `show` is intentionally unauthenticated — branding (logo, colours,
 * platform name) must be readable by a login screen before the user
 * has a session. Only `update` requires core.branding.manage.
 */
final class BrandingController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly BrandingService $branding,
    ) {}

    public function show(): JsonResponse
    {
        return $this->ok(['data' => $this->branding->current()]);
    }

    public function update(UpdateBrandingRequest $request): JsonResponse
    {
        return $this->ok(['data' => $this->branding->update($request->validated())]);
    }
}
