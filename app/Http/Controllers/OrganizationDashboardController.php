<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\OrganizationDashboardService;
use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class OrganizationDashboardController
{
    public function __construct(
        private readonly OrganizationDashboardService $dashboard,
        private readonly PermissionResolver $permissions,
    ) {}

    public function __invoke(Request $request): View
    {
        $membership = $request->attributes->get('organization_membership');
        abort_unless($membership instanceof Membership, 403);
        abort_unless($this->permissions->allows($membership, 'organization.dashboard.view'), 403);

        return view('dashboard', $this->dashboard->for($membership));
    }
}
