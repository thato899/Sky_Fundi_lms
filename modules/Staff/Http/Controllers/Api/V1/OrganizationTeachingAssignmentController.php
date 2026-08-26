<?php

declare(strict_types=1);

namespace Modules\Staff\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Staff\Application\TeachingAssignmentService;
use Modules\Staff\Http\Requests\BulkStoreTeachingAssignmentRequest;
use Modules\Staff\Http\Resources\TeachingAssignmentResource;
use Modules\Staff\Infrastructure\Models\TeachingAssignment;

/**
 * Organization-wide teaching-assignment admin surface: a filterable list
 * across every staff member (the per-staff endpoints in
 * TeachingAssignmentController stay as-is for the staff profile page) and
 * best-effort bulk creation for onboarding a term's worth of coverage at
 * once.
 */
final class OrganizationTeachingAssignmentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly TeachingAssignmentService $assignments,
        private readonly PermissionResolver $permissions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $organization = $this->organization($request);
        $membership = $request->attributes->get('organization_membership');
        abort_unless($membership instanceof Membership && $this->permissions->allows($membership, 'teaching_assignments.view'), 403);

        $query = TeachingAssignment::query()
            ->with(['staffProfile', 'academicYear', 'classGroup', 'subject'])
            ->where('organization_id', $organization->getKey());

        foreach (['staff_profile_id', 'class_id', 'subject_id', 'academic_year_id'] as $filter) {
            if (is_string($value = $request->query($filter)) && $value !== '') {
                $query->where($filter, $value);
            }
        }
        if ($request->boolean('active_only')) {
            $query->whereNull('ended_on');
        }

        return $this->ok(TeachingAssignmentResource::collection(
            $query->orderByDesc('ended_on')->orderByDesc('started_on')->paginate(25)
        ));
    }

    public function bulkStore(BulkStoreTeachingAssignmentRequest $request): JsonResponse
    {
        $organization = $this->organization($request);
        $result = $this->assignments->assignMany($organization, $request->validated('assignments'), $request->user());

        return $this->created([
            'created' => collect($result['created'])
                ->map(fn (TeachingAssignment $assignment) => (new TeachingAssignmentResource(
                    $assignment->load(['academicYear', 'classGroup', 'subject'])
                ))->resolve())
                ->all(),
            'failed' => $result['failed'],
        ]);
    }

    private function organization(Request $request): Organization
    {
        $organization = $request->attributes->get('organization');
        abort_unless($organization instanceof Organization, 403);

        return $organization;
    }
}
