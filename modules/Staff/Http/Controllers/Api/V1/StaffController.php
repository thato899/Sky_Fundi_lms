<?php

declare(strict_types=1);

namespace Modules\Staff\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Staff\Application\StaffService;
use Modules\Staff\Http\Requests\StoreStaffRequest;
use Modules\Staff\Http\Requests\UpdateStaffRequest;
use Modules\Staff\Http\Resources\StaffResource;
use Modules\Staff\Infrastructure\Models\StaffProfile;

final class StaffController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly StaffService $staff) {}

    public function index(Request $request): JsonResponse
    {
        $org = $request->attributes->get('organization');
        abort_unless($org instanceof Organization, 403);

        return $this->ok(StaffResource::collection(StaffProfile::query()->where('organization_id', $org->getKey())->when($request->input('search'), fn ($q, $s) => $q->where('employee_number', 'like', "%$s%"))->paginate(25)));
    }

    public function store(StoreStaffRequest $request): JsonResponse
    {
        $org = $request->attributes->get('organization');
        abort_unless($org instanceof Organization, 403);

        return $this->created(new StaffResource($this->staff->create($org, $request->validated(), $request->user()?->getAuthIdentifier())));
    }

    public function update(UpdateStaffRequest $request, StaffProfile $staff): JsonResponse
    {
        $this->guard($request, $staff);

        return $this->ok(new StaffResource($this->staff->update($staff, $request->validated())));
    }

    public function status(Request $request, StaffProfile $staff, string $status): JsonResponse
    {
        $this->guard($request, $staff);

        return $this->ok(new StaffResource($this->staff->transition($staff, $status)));
    }

    private function guard(Request $request, StaffProfile $staff): void
    {
        abort_unless($request->attributes->get('organization')?->getKey() === $staff->getAttribute('organization_id'), 404);
    }
}
