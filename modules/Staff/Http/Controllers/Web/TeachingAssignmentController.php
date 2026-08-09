<?php

declare(strict_types=1);

namespace Modules\Staff\Http\Controllers\Web;

use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Support\Exceptions\DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Organizations\Application\OrganizationService;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Staff\Application\TeachingAssignmentService;
use Modules\Staff\Http\Requests\StoreTeachingAssignmentRequest;
use Modules\Staff\Infrastructure\Models\StaffProfile;
use Modules\Staff\Infrastructure\Models\TeachingAssignment;

final class TeachingAssignmentController
{
    public function __construct(
        private readonly TeachingAssignmentService $assignments,
        private readonly PermissionResolver $permissions,
        private readonly OrganizationService $organizations,
    ) {}

    public function index(Request $request, string $staff): View
    {
        [$organization, $membership] = $this->context($request, 'teaching_assignments.view');
        $profile = $this->staff($organization, $staff);

        return view('staff.assignments', $this->shared($organization, $membership) + [
            'staffProfile' => $profile,
            'assignments' => TeachingAssignment::query()->with(['academicYear', 'classGroup', 'subject'])
                ->where('organization_id', $organization->getKey())->where('staff_profile_id', $profile->getKey())
                ->orderByDesc('ended_on')->orderByDesc('started_on')->get(),
            'academicYears' => AcademicYear::query()->where('organization_id', $organization->getKey())->orderByDesc('start_date')->get(['id', 'name']),
            'classes' => ClassGroup::query()->with('academicYear:id,name')->where('organization_id', $organization->getKey())->orderBy('name')->get(['id', 'name', 'academic_year_id']),
            'subjects' => Subject::query()->where('organization_id', $organization->getKey())->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function store(StoreTeachingAssignmentRequest $request, string $staff): RedirectResponse
    {
        [$organization] = $this->context($request, 'teaching_assignments.manage');
        $profile = $this->staff($organization, $staff);

        try {
            $this->assignments->assign($organization, $profile, $request->validated(), $request->user());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['assignment' => $exception->getMessage()]);
        }

        return redirect()->route('staff.assignments.index', $profile)->with('status', 'Teaching assignment added.');
    }

    public function end(Request $request, string $staff, string $assignment): RedirectResponse
    {
        [$organization] = $this->context($request, 'teaching_assignments.manage');
        $profile = $this->staff($organization, $staff);
        $record = TeachingAssignment::query()->where('organization_id', $organization->getKey())
            ->where('staff_profile_id', $profile->getKey())->findOrFail($assignment);

        try {
            $this->assignments->end($record, $request->user());
        } catch (DomainException $exception) {
            return back()->withErrors(['assignment' => $exception->getMessage()]);
        }

        return redirect()->route('staff.assignments.index', $profile)->with('status', 'Teaching assignment ended.');
    }

    private function context(Request $request, string $permission): array
    {
        $membership = $request->attributes->get('organization_membership');
        $organization = $request->attributes->get('organization');
        abort_unless($membership instanceof Membership && $organization instanceof Organization, 403);
        abort_unless($this->permissions->allows($membership, $permission), 403);

        return [$organization, $membership];
    }

    private function staff(Organization $organization, string $id): StaffProfile
    {
        return StaffProfile::query()->where('organization_id', $organization->getKey())->findOrFail($id);
    }

    private function shared(Organization $organization, Membership $membership): array
    {
        return ['branding' => $this->organizations->branding($organization), 'organization' => $organization, 'membership' => $membership, 'permissions' => $this->permissions->permissions($membership)];
    }
}
