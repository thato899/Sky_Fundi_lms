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
use Modules\Leaderboards\Application\SportspersonService;
use Modules\Leaderboards\Infrastructure\Models\SportspersonOfTheWeek;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Application\OrganizationService;
use Modules\Organizations\Infrastructure\Models\Organization;

final class SportspersonController
{
    public function __construct(
        private readonly SportspersonService $sportsperson,
        private readonly PermissionResolver $permissions,
        private readonly OrganizationService $organizations,
    ) {}

    public function index(Request $request): View
    {
        [$organization, $membership] = $this->context($request);
        $canManage = $this->permissions->allows($membership, 'leaderboards.manage');
        $query = SportspersonOfTheWeek::query()->where('organization_id', $organization->getKey())->with('learner');
        if (! $canManage) {
            $query->where('is_published', true);
        }

        return view('leaderboards.sportsperson', $this->shared($organization, $membership) + [
            'canManage' => $canManage,
            'posts' => $query->orderByDesc('week_start_date')->get(),
            'learners' => $canManage ? LearnerProfile::query()->where('organization_id', $organization->getKey())->where('learner_status', 'active')->orderBy('first_name')->get(['id', 'first_name', 'last_name']) : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'leaderboards.manage'), 403);
        $data = $request->validate([
            'learner_profile_id' => ['required', 'uuid'],
            'week_start_date' => ['required', 'date'],
            'citation' => ['required', 'string', 'max:2000'],
        ]);
        try {
            $this->sportsperson->post($organization, $this->actor($request), $data);
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['sportsperson' => $exception->getMessage()]);
        }

        return back()->with('status', 'Sportsperson of the Week posted — publish it when you are ready to announce it.');
    }

    public function publish(Request $request, SportspersonOfTheWeek $post): RedirectResponse
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'leaderboards.manage'), 403);
        abort_unless($post->getAttribute('organization_id') === $organization->getKey(), 404);
        $this->sportsperson->publish($post, $this->actor($request));

        return back()->with('status', 'Sportsperson of the Week announced to the school.');
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
