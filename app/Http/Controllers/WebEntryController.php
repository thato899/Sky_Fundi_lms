<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\PostLoginDestinationResolver;
use Core\Branding\Application\BrandingService;
use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Organizations\Application\OrganizationService;
use Modules\Organizations\Infrastructure\Models\Organization;

final class WebEntryController
{
    public function __construct(
        private readonly BrandingService $branding,
        private readonly PostLoginDestinationResolver $destinations,
        private readonly PermissionResolver $permissions,
        private readonly OrganizationService $organizations,
    ) {}

    public function home(Request $request): View|RedirectResponse
    {
        if ($request->user() instanceof User) {
            return $this->destinations->redirect($request->user(), $request);
        }

        return view('home', ['branding' => $this->branding->current()]);
    }

    public function access(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $memberships = $this->destinations->activeMemberships($user);

        if ($memberships->count() === 1) {
            return $this->destinations->redirect($user, $request);
        }

        return view('auth.access', [
            'branding' => $this->branding->current(),
            'memberships' => $memberships,
            'user' => $user,
        ]);
    }

    public function selectOrganization(Request $request): RedirectResponse
    {
        $validated = $request->validate(['organization_id' => ['required', 'uuid']]);

        return $this->destinations->select($request->user(), $request, $validated['organization_id']);
    }

    public function dashboard(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $organizationId = $request->session()->get('organization_id');
        $membership = $organizationId
            ? $this->destinations->activeMemberships($user)->firstWhere('organization_id', $organizationId)
            : null;

        if (! $membership instanceof Membership) {
            return $this->destinations->redirect($user, $request);
        }

        $organization = $membership->getRelation('organization');
        abort_unless($organization instanceof Organization, 404);

        return view('dashboard', [
            'branding' => $this->organizations->branding($organization),
            'membership' => $membership,
            'permissions' => $this->permissions->permissions($membership),
            'user' => $user,
        ]);
    }
}
