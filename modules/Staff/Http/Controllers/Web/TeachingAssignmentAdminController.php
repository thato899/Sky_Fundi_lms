<?php

declare(strict_types=1);

namespace Modules\Staff\Http\Controllers\Web;

use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Organizations\Application\OrganizationService;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Staff\Application\TeachingAssignmentService;
use Modules\Staff\Infrastructure\Models\StaffProfile;
use Modules\Staff\Infrastructure\Models\TeachingAssignment;

/**
 * Organization-wide teaching-assignment admin surface (filterable list
 * across every staff member) and a fixed-size bulk-entry grid for
 * assigning several staff/class/subject combinations in one submission.
 * The per-staff assignment page (TeachingAssignmentController) is
 * unchanged and remains the single-assignment entry point from a staff
 * profile.
 */
final class TeachingAssignmentAdminController
{
    /** Blank rows rendered in the bulk-entry grid. */
    private const BULK_ROWS = 10;

    public function __construct(
        private readonly TeachingAssignmentService $assignments,
        private readonly PermissionResolver $permissions,
        private readonly OrganizationService $organizations,
    ) {}

    public function index(Request $request): View
    {
        [$organization, $membership] = $this->context($request, 'teaching_assignments.view');

        $query = TeachingAssignment::query()->with(['staffProfile', 'academicYear', 'classGroup', 'subject'])
            ->where('organization_id', $organization->getKey());
        foreach (['staff_profile_id', 'class_id', 'subject_id', 'academic_year_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }
        if ($request->boolean('active_only')) {
            $query->whereNull('ended_on');
        }

        return view('staff.teaching-assignments-admin', $this->shared($organization, $membership) + [
            'assignments' => $query->orderByDesc('ended_on')->orderByDesc('started_on')->paginate(25)->withQueryString(),
            'staffMembers' => StaffProfile::query()->where('organization_id', $organization->getKey())
                ->whereIn('employment_status', ['active', 'probation'])->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'academicYears' => AcademicYear::query()->where('organization_id', $organization->getKey())->orderByDesc('start_date')->get(['id', 'name']),
            'classes' => ClassGroup::query()->with('academicYear:id,name')->where('organization_id', $organization->getKey())->orderBy('name')->get(['id', 'name', 'academic_year_id']),
            'subjects' => Subject::query()->where('organization_id', $organization->getKey())->orderBy('name')->get(['id', 'name', 'code']),
            'bulkRows' => self::BULK_ROWS,
            'bulkFailures' => $request->session()->get('bulkFailures', []),
        ]);
    }

    public function bulkStore(Request $request): RedirectResponse
    {
        [$organization] = $this->context($request, 'teaching_assignments.manage');

        $rows = collect($request->input('assignments', []))
            ->filter(fn (mixed $row): bool => is_array($row)
                && filled(Arr::get($row, 'staff_profile_id'))
                && filled(Arr::get($row, 'class_id'))
                && filled(Arr::get($row, 'academic_year_id')))
            ->values()->all();

        if ($rows === []) {
            return back()->withErrors(['assignments' => 'Add at least one complete row (staff, class, and academic year).']);
        }

        $result = $this->assignments->assignMany($organization, $rows, $request->user());
        $status = count($result['created']).' teaching assignment(s) created.';
        if ($result['failed'] !== []) {
            $status .= ' '.count($result['failed']).' row(s) failed — see details below.';
        }

        return redirect()->route('teaching-assignments.index')->with('status', $status)->with('bulkFailures', $result['failed']);
    }

    private function context(Request $request, string $permission): array
    {
        $membership = $request->attributes->get('organization_membership');
        $organization = $request->attributes->get('organization');
        abort_unless($membership instanceof Membership && $organization instanceof Organization, 403);
        abort_unless($this->permissions->allows($membership, $permission), 403);

        return [$organization, $membership];
    }

    private function shared(Organization $organization, Membership $membership): array
    {
        return ['branding' => $this->organizations->branding($organization), 'organization' => $organization, 'membership' => $membership, 'permissions' => $this->permissions->permissions($membership)];
    }
}
