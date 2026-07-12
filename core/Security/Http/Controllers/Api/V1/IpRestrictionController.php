<?php

declare(strict_types=1);

namespace Core\Security\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Security\Application\IpRestrictionService;
use Core\Security\Domain\Enums\IpRestrictionType;
use Core\Security\Http\Requests\AddIpRestrictionRequest;
use Core\Security\Http\Resources\IpRestrictionResource;
use Core\Security\Infrastructure\Models\IpRestriction;
use Illuminate\Http\JsonResponse;

final class IpRestrictionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly IpRestrictionService $restrictions,
    ) {}

    public function index(): JsonResponse
    {
        return $this->ok(IpRestrictionResource::collection(IpRestriction::query()->latest()->get()));
    }

    public function store(AddIpRestrictionRequest $request): JsonResponse
    {
        $restriction = $this->restrictions->add(
            type: IpRestrictionType::from($request->string('type')->value()),
            ipCidr: $request->string('ip_cidr')->value(),
            scopeType: $request->string('scope_type', 'platform')->value(),
            scopeId: $request->string('scope_id')->value() ?: null,
            description: $request->string('description')->value() ?: null,
        );

        return $this->created(new IpRestrictionResource($restriction));
    }

    public function destroy(IpRestriction $ipRestriction): JsonResponse
    {
        $this->restrictions->remove($ipRestriction);

        return $this->noContent();
    }
}
