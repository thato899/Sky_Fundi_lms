<?php

declare(strict_types=1);

namespace App\Application;

use Core\Identity\Domain\Enums\MembershipStatus;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PostLoginDestinationResolver
{
    public function __construct(private readonly NavigationContext $navigation) {}

    public function redirect(User $user, Request $request): RedirectResponse
    {
        if ($user->can('core.roles.manage')) {
            $request->session()->forget('organization_id');

            return redirect()->route('super-admin.dashboard');
        }

        $memberships = $this->activeMemberships($user);

        if ($memberships->count() === 1) {
            $organizationId = (string) $memberships->first()->getAttribute('organization_id');
            $request->session()->put('organization_id', $organizationId);

            return $this->destination($user, $organizationId);
        }

        $request->session()->forget('organization_id');

        return redirect()->route('access');
    }

    public function select(User $user, Request $request, string $organizationId): RedirectResponse
    {
        $membership = $this->activeMemberships($user)
            ->firstWhere('organization_id', $organizationId);

        abort_unless($membership instanceof Membership, 404);
        $request->session()->put('organization_id', $membership->getAttribute('organization_id'));

        return $this->destination($user, (string) $membership->getAttribute('organization_id'));
    }

    /**
     * Learners land on their quiz list, guardians on their portal page, and
     * staff on the first surface their permissions allow (teachers without
     * the dashboard permission land on Assessments instead of a 403).
     */
    private function destination(User $user, string $organizationId): RedirectResponse
    {
        $navigation = $this->navigation->for($user, $organizationId);
        $first = $navigation['links'][0]['href'] ?? null;

        return $first !== null ? redirect()->to($first) : redirect()->route('dashboard');
    }

    /** @return Collection<int, Membership> */
    public function activeMemberships(User $user): Collection
    {
        return $user->memberships()
            ->with(['organization.settings', 'role.permissions'])
            ->where('status', MembershipStatus::Active->value)
            ->whereHas('organization', fn ($query) => $query->where('status', 'active'))
            ->orderByDesc('is_default')
            ->get();
    }
}
