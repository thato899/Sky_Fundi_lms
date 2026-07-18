<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\HackathonSubscriptionService;
use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Organizations\Application\OrganizationService;
use Modules\Organizations\Infrastructure\Models\Organization;

final class HackathonSubscriptionController
{
    public function __construct(private readonly HackathonSubscriptionService $service, private readonly PermissionResolver $permissions, private readonly OrganizationService $organizations) {}

    public function __invoke(Request $request): View
    {
        $organization = $request->attributes->get('organization');
        $membership = $request->attributes->get('organization_membership');
        abort_unless($organization instanceof Organization && $membership instanceof Membership && $this->permissions->allows($membership, 'subscriptions.view'), 403);

        return view('subscriptions.dashboard', $this->service->for($organization) + ['organization' => $organization, 'branding' => $this->organizations->branding($organization), 'permissions' => $this->permissions->permissions($membership)]);
    }
}
