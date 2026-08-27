<?php

declare(strict_types=1);

namespace Modules\Leaderboards\Http\Controllers\Web;

use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Leaderboards\Application\SportsRecordService;
use Modules\Leaderboards\Infrastructure\Models\SportsRecord;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Application\OrganizationService;
use Modules\Organizations\Infrastructure\Models\Organization;

final class SportsRecordController
{
    public function __construct(
        private readonly SportsRecordService $records,
        private readonly PermissionResolver $permissions,
        private readonly OrganizationService $organizations,
    ) {}

    public function index(Request $request): View
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'sports_records.manage'), 403);

        return view('leaderboards.sports-records', $this->shared($organization, $membership) + [
            'records' => SportsRecord::query()->where('organization_id', $organization->getKey())->with('learner')->latest('event_date')->limit(100)->get(),
            'learners' => LearnerProfile::query()->where('organization_id', $organization->getKey())->where('learner_status', 'active')->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'sports_records.manage'), 403);
        $data = $request->validate([
            'learner_profile_id' => ['required', 'uuid'],
            'activity' => ['required', 'string', 'max:255'],
            'points' => ['required', 'numeric', 'min:0', 'max:9999'],
            'event_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        try {
            $this->records->record($organization, $this->actor($request), $data);
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['sports_record' => $exception->getMessage()]);
        }

        return back()->with('status', 'Sports result logged.');
    }

    private function context(Request $request): array
    {
        $organization = $request->attributes->get('organization');
        $membership = $request->attributes->get('organization_membership');
        abort_unless($organization instanceof Organization && $membership instanceof Membership, 403);

        return [$organization, $membership];
    }

    private function shared(Organization $organization, Membership $membership): array
    {
        return ['organization' => $organization, 'branding' => $this->organizations->branding($organization), 'permissions' => $this->permissions->permissions($membership)];
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
