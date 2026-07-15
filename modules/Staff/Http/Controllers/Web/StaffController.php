<?php

declare(strict_types=1);

namespace Modules\Staff\Http\Controllers\Web;

use Core\AuditLogs\Infrastructure\Models\AuditLog;
use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Academics\Infrastructure\Models\Department;
use Modules\Organizations\Application\OrganizationService;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Staff\Application\StaffService;
use Modules\Staff\Http\Requests\StoreStaffRequest;
use Modules\Staff\Http\Requests\UpdateStaffRequest;
use Modules\Staff\Infrastructure\Models\StaffProfile;

final class StaffController
{
    public function __construct(
        private readonly StaffService $staff,
        private readonly PermissionResolver $permissions,
        private readonly OrganizationService $organizations,
    ) {}

    public function index(Request $request): View
    {
        [$organization, $membership] = $this->context($request, 'staff.view');
        $sorts = ['employee_number', 'first_name', 'last_name', 'created_at'];
        $sort = in_array($request->string('sort')->toString(), $sorts, true) ? $request->string('sort')->toString() : 'created_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $query = StaffProfile::query()->with(['user', 'department'])->where('organization_id', $organization->getKey());
        $search = trim($request->string('search')->toString());
        $query->when($search !== '', fn ($q) => $q->where(fn ($nested) => $nested
            ->where('employee_number', 'like', "%{$search}%")->orWhere('first_name', 'like', "%{$search}%")
            ->orWhere('last_name', 'like', "%{$search}%")->orWhere('work_email', 'like', "%{$search}%")
            ->orWhere('staff_type', 'like', "%{$search}%")->orWhere('employment_status', 'like', "%{$search}%")
            ->orWhereHas('department', fn ($department) => $department->where('name', 'like', "%{$search}%"))));
        foreach (['department_id', 'employment_status', 'staff_type'] as $filter) {
            $query->when($request->filled($filter), fn ($q) => $q->where($filter, $request->input($filter)));
        }
        $query->when($request->filled('portal_access_enabled'), fn ($q) => $q->where('portal_access_enabled', $request->boolean('portal_access_enabled')));

        return view('staff.index', $this->shared($organization, $membership) + [
            'staff' => $query->orderBy($sort, $direction)->paginate(15)->withQueryString(),
            'departments' => $this->departments($organization),
        ]);
    }

    public function create(Request $request): View
    {
        [$organization, $membership] = $this->context($request, 'staff.create');

        return view('staff.form', $this->shared($organization, $membership) + ['staffProfile' => null, 'departments' => $this->departments($organization)]);
    }

    public function store(StoreStaffRequest $request): RedirectResponse
    {
        [$organization] = $this->context($request, 'staff.create');
        $staff = $this->staff->create($organization, $request->validated(), (string) $request->user()?->getAuthIdentifier());

        return redirect()->route('staff.show', $staff)->with('status', 'Staff member created.');
    }

    public function show(Request $request, string $staff): View
    {
        [$organization, $membership] = $this->context($request, 'staff.view');
        $profile = $this->resolve($organization, $staff);
        $activity = AuditLog::query()->with('actor:id,name')->where('target_type', StaffProfile::class)->where('target_id', $profile->getKey())->latest()->limit(8)->get();

        return view('staff.show', $this->shared($organization, $membership) + ['staffProfile' => $profile, 'activity' => $activity]);
    }

    public function edit(Request $request, string $staff): View
    {
        [$organization, $membership] = $this->context($request, 'staff.update');

        return view('staff.form', $this->shared($organization, $membership) + ['staffProfile' => $this->resolve($organization, $staff), 'departments' => $this->departments($organization)]);
    }

    public function update(UpdateStaffRequest $request, string $staff): RedirectResponse
    {
        [$organization] = $this->context($request, 'staff.update');
        $profile = $this->staff->update($this->resolve($organization, $staff), $request->validated());

        return redirect()->route('staff.show', $profile)->with('status', 'Staff member updated.');
    }

    public function suspend(Request $request, string $staff): RedirectResponse
    {
        return $this->transition($request, $staff, 'suspended');
    }

    public function activate(Request $request, string $staff): RedirectResponse
    {
        return $this->transition($request, $staff, 'active');
    }

    private function transition(Request $request, string $staff, string $status): RedirectResponse
    {
        [$organization] = $this->context($request, 'staff.manage_employment');
        $profile = $this->staff->transition($this->resolve($organization, $staff), $status);

        return redirect()->route('staff.show', $profile)->with('status', "Staff member {$status}.");
    }

    private function resolve(Organization $organization, string $id): StaffProfile
    {
        return StaffProfile::query()->with(['user', 'membership.role', 'department', 'organization'])->where('organization_id', $organization->getKey())->findOrFail($id);
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

    private function departments(Organization $organization)
    {
        return Department::query()->withoutGlobalScopes()->where('organization_id', $organization->getKey())->orderBy('name')->get(['id', 'name']);
    }
}
