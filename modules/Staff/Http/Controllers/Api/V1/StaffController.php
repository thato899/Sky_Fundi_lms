<?php

declare(strict_types=1);

namespace Modules\Staff\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Staff\Application\StaffService;
use Modules\Staff\Infrastructure\Models\StaffProfile;

final class StaffController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly StaffService $staff) {}

    public function index(Request $request): JsonResponse
    {
        $org = $request->attributes->get('organization');
        abort_unless($org, 403);

        return $this->ok(StaffProfile::query()->where('organization_id', $org->id)->when($request->search, fn ($q, $s) => $q->where('employee_number', 'like', "%$s%"))->paginate(25));
    }

    public function store(Request $request): JsonResponse
    {
        $org = $request->attributes->get('organization');
        $data = $request->validate(['organization_membership_id' => 'required|uuid', 'user_id' => 'required|uuid', 'employee_number' => 'required|string', 'staff_type' => 'required|string', 'job_title' => 'nullable|string', 'employment_type' => 'nullable|string', 'department_id' => 'nullable|uuid']);
        $data['organization_id'] = $org->id;

        return $this->created($this->staff->create($data));
    }

    public function update(Request $request, StaffProfile $staff): JsonResponse
    {
        $this->guard($request, $staff);
        $staff->update($request->validate(['job_title' => 'sometimes|string', 'staff_type' => 'sometimes|string', 'department_id' => 'nullable|uuid', 'work_phone' => 'nullable|string', 'qualification_summary' => 'nullable|string']));

        return $this->ok($staff->fresh());
    }

    public function status(Request $request, StaffProfile $staff, string $status): JsonResponse
    {
        $this->guard($request, $staff);

        return $this->ok($this->staff->transition($staff, $status));
    }

    private function guard(Request $request, StaffProfile $staff): void
    {
        abort_unless($request->attributes->get('organization')?->id === $staff->organization_id, 404);
    }
}
