<?php

declare(strict_types=1);

namespace Modules\Staff\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Support\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Staff\Application\TeachingAssignmentService;
use Modules\Staff\Http\Requests\StoreTeachingAssignmentRequest;
use Modules\Staff\Http\Resources\TeachingAssignmentResource;
use Modules\Staff\Infrastructure\Models\StaffProfile;
use Modules\Staff\Infrastructure\Models\TeachingAssignment;

final class TeachingAssignmentController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TeachingAssignmentService $assignments) {}

    public function index(Request $request, StaffProfile $staff): JsonResponse
    {
        $organization = $this->guard($request, $staff);

        return $this->ok(TeachingAssignmentResource::collection(TeachingAssignment::query()->with(['academicYear', 'classGroup', 'subject'])
            ->where('organization_id', $organization->getKey())->where('staff_profile_id', $staff->getKey())
            ->orderByDesc('ended_on')->orderByDesc('started_on')->paginate(25)));
    }

    public function store(StoreTeachingAssignmentRequest $request, StaffProfile $staff): JsonResponse
    {
        $organization = $this->guard($request, $staff);

        try {
            $assignment = $this->assignments->assign($organization, $staff, $request->validated(), $request->user());
        } catch (DomainException $exception) {
            return $this->message($exception->getMessage(), 422);
        }

        return $this->created(new TeachingAssignmentResource($assignment->load(['academicYear', 'classGroup', 'subject'])));
    }

    public function end(Request $request, StaffProfile $staff, TeachingAssignment $assignment): JsonResponse
    {
        $organization = $this->guard($request, $staff);
        abort_unless($assignment->getAttribute('organization_id') === $organization->getKey() && $assignment->getAttribute('staff_profile_id') === $staff->getKey(), 404);

        try {
            $ended = $this->assignments->end($assignment, $request->user());
        } catch (DomainException $exception) {
            return $this->message($exception->getMessage(), 422);
        }

        return $this->ok(new TeachingAssignmentResource($ended->load(['academicYear', 'classGroup', 'subject'])));
    }

    private function guard(Request $request, StaffProfile $staff): Organization
    {
        $organization = $request->attributes->get('organization');
        abort_unless($organization instanceof Organization && $organization->getKey() === $staff->getAttribute('organization_id'), 404);

        return $organization;
    }
}
