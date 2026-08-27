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
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Leaderboards\Application\LeaderboardService;
use Modules\Leaderboards\Infrastructure\Models\Leaderboard;
use Modules\Leaderboards\Infrastructure\Models\LeaderboardEntry;
use Modules\Learners\Infrastructure\Models\GuardianProfile;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Application\OrganizationService;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Reports\Infrastructure\Models\ReportingPeriod;

final class LeaderboardController
{
    public function __construct(
        private readonly LeaderboardService $leaderboards,
        private readonly PermissionResolver $permissions,
        private readonly OrganizationService $organizations,
    ) {}

    /** The principal's (or a view-permitted staff member's) management view. */
    public function index(Request $request): View
    {
        [$organization, $membership] = $this->context($request);
        $this->authorize($membership, ['leaderboards.manage', 'leaderboards.view_organization']);

        return view('leaderboards.index', $this->shared($organization, $membership) + [
            'canManage' => $this->permissions->allows($membership, 'leaderboards.manage'),
            'leaderboards' => Leaderboard::query()->where('organization_id', $organization->getKey())->with(['subject', 'grade', 'classGroup'])->latest('generated_at')->get(),
            'reportingPeriods' => ReportingPeriod::query()->where('organization_id', $organization->getKey())->orderByDesc('start_date')->get(),
            'subjects' => Subject::query()->where('organization_id', $organization->getKey())->orderBy('name')->get(['id', 'name']),
            'grades' => Grade::query()->where('organization_id', $organization->getKey())->orderBy('order')->get(['id', 'name']),
            'classes' => ClassGroup::query()->where('organization_id', $organization->getKey())->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeAcademic(Request $request): RedirectResponse
    {
        [$organization, $membership] = $this->context($request);
        $this->authorize($membership, ['leaderboards.manage']);
        $data = $request->validate([
            'reporting_period_id' => ['required', 'uuid'],
            'subject_id' => ['nullable', 'uuid'],
            'grade_id' => ['nullable', 'uuid'],
            'class_id' => ['nullable', 'uuid'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);
        try {
            $this->leaderboards->generateAcademic($organization, $this->actor($request), $data);
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['academic' => $exception->getMessage()]);
        }

        return redirect()->route('leaderboards.index')->with('status', 'Academic leaderboard generated — it stays private until you publish it.');
    }

    public function storeSports(Request $request): RedirectResponse
    {
        [$organization, $membership] = $this->context($request);
        $this->authorize($membership, ['leaderboards.manage']);
        $data = $request->validate([
            'period_start_date' => ['required', 'date'],
            'period_end_date' => ['required', 'date', 'after_or_equal:period_start_date'],
            'grade_id' => ['nullable', 'uuid'],
            'class_id' => ['nullable', 'uuid'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);
        try {
            $this->leaderboards->generateSports($organization, $this->actor($request), $data);
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['sports' => $exception->getMessage()]);
        }

        return redirect()->route('leaderboards.index')->with('status', 'Sports leaderboard generated — it stays private until you publish it.');
    }

    public function publish(Request $request, Leaderboard $leaderboard): RedirectResponse
    {
        [$organization, $membership] = $this->context($request);
        $this->authorize($membership, ['leaderboards.manage']);
        $this->guard($organization, $leaderboard);
        $this->leaderboards->publish($leaderboard, $this->actor($request));

        return back()->with('status', 'Leaderboard published — every learner in scope can now see the full standings.');
    }

    public function unpublish(Request $request, Leaderboard $leaderboard): RedirectResponse
    {
        [$organization, $membership] = $this->context($request);
        $this->authorize($membership, ['leaderboards.manage']);
        $this->guard($organization, $leaderboard);
        $this->leaderboards->unpublish($leaderboard, $this->actor($request));

        return back()->with('status', 'Leaderboard made private again — only you and each learner\'s own position are visible now.');
    }

    /**
     * The one route every audience shares. Managers and view-permission
     * holders always see the full ranked list. Everyone else sees the
     * full list only if the leaderboard is published — otherwise only
     * their own entry (a learner) or their linked children's entries (a
     * guardian), never anyone else's, per the visibility contract in
     * modules/Leaderboards/README.md.
     */
    public function show(Request $request, Leaderboard $leaderboard): View
    {
        [$organization, $membership] = $this->context($request);
        $this->guard($organization, $leaderboard);
        $leaderboard->load(['entries.learner', 'subject', 'grade', 'classGroup', 'reportingPeriod']);

        $canManage = $this->permissions->allows($membership, 'leaderboards.manage');
        $canViewFull = $canManage || $this->permissions->allows($membership, 'leaderboards.view_organization');
        $fullyVisible = $leaderboard->isPublished() || $canViewFull;

        $viewerLearnerIds = $this->viewerLearnerIds($request, $organization);
        $entries = $fullyVisible ? $leaderboard->entries : $leaderboard->entries->whereIn('learner_profile_id', $viewerLearnerIds)->values();

        return view('leaderboards.show', $this->shared($organization, $membership) + [
            'leaderboard' => $leaderboard,
            'entries' => $entries,
            'fullyVisible' => $fullyVisible,
            'canManage' => $canManage,
            'viewerLearnerIds' => $viewerLearnerIds,
        ]);
    }

    /** A learner's or guardian's own convenience page: every leaderboard they appear on. */
    public function mine(Request $request): View
    {
        [$organization, $membership] = $this->context($request);
        $learnerIds = $this->viewerLearnerIds($request, $organization);
        abort_if($learnerIds === [], 403);

        $entries = LeaderboardEntry::query()
            ->where('organization_id', $organization->getKey())
            ->whereIn('learner_profile_id', $learnerIds)
            ->with(['leaderboard', 'learner'])
            ->get()
            ->groupBy('leaderboard_id');

        return view('leaderboards.mine', $this->shared($organization, $membership) + ['entriesByLeaderboard' => $entries]);
    }

    /** @return list<string> */
    private function viewerLearnerIds(Request $request, Organization $organization): array
    {
        $user = $this->actor($request);
        $ownLearner = LearnerProfile::query()->where('organization_id', $organization->getKey())->where('user_id', $user->getKey())->first();
        if ($ownLearner !== null) {
            return [$ownLearner->getKey()];
        }
        $guardian = GuardianProfile::query()->where('organization_id', $organization->getKey())->where('user_id', $user->getKey())->first();
        if ($guardian !== null) {
            return $guardian->relationships()->where('status', 'active')->pluck('learner_profile_id')->all();
        }

        return [];
    }

    private function guard(Organization $organization, Leaderboard $leaderboard): void
    {
        abort_unless($leaderboard->getAttribute('organization_id') === $organization->getKey(), 404);
    }

    private function authorize(Membership $membership, array $anyOf): void
    {
        foreach ($anyOf as $permission) {
            if ($this->permissions->allows($membership, $permission)) {
                return;
            }
        }
        abort(403);
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
