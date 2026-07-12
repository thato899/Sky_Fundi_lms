<?php

declare(strict_types=1);

namespace Core\FeatureFlags\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\FeatureFlags\Application\FeatureFlagService;
use Core\FeatureFlags\Domain\Enums\FeatureFlagScope;
use Core\FeatureFlags\Http\Requests\DefineFeatureFlagRequest;
use Core\FeatureFlags\Http\Requests\ToggleFeatureFlagRequest;
use Core\FeatureFlags\Http\Resources\FeatureFlagResource;
use Core\FeatureFlags\Infrastructure\Models\FeatureFlag;
use Illuminate\Http\JsonResponse;

final class FeatureFlagController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly FeatureFlagService $flags,
    ) {}

    public function index(): JsonResponse
    {
        return $this->ok(FeatureFlagResource::collection(
            FeatureFlag::query()->with('overrides')->orderBy('key')->get(),
        ));
    }

    public function store(DefineFeatureFlagRequest $request): JsonResponse
    {
        $flag = $this->flags->define(
            key: $request->string('key')->value(),
            name: $request->string('name')->value(),
            description: $request->string('description')->value() ?: null,
        );

        return $this->created(new FeatureFlagResource($flag));
    }

    public function toggle(ToggleFeatureFlagRequest $request, FeatureFlag $featureFlag): JsonResponse
    {
        $scopeType = $request->string('scope_type')->value();

        if ($scopeType === '') {
            $flag = $this->flags->setGlobal($featureFlag, $request->boolean('enabled'));

            return $this->ok(new FeatureFlagResource($flag->load('overrides')));
        }

        $this->flags->setForScope(
            $featureFlag,
            FeatureFlagScope::from($scopeType),
            $request->string('scope_id')->value(),
            $request->boolean('enabled'),
        );

        return $this->ok(new FeatureFlagResource($featureFlag->load('overrides')));
    }
}
