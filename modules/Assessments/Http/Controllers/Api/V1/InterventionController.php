<?php

declare(strict_types=1);

namespace Modules\Assessments\Http\Controllers\Api\V1;

use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Assessments\Application\InterventionDashboardService;
use Modules\Organizations\Infrastructure\Models\Organization;

final class InterventionController
{
    public function __construct(
        private readonly InterventionDashboardService $dashboard,
        private readonly PermissionResolver $permissions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        [$organization, $membership, $actor] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'interventions.view'), 403);

        return response()->json(['data' => $this->dashboard->dashboard(
            $organization->getKey(),
            $actor,
            $this->permissions->allows($membership, 'interventions.view_organization'),
        )]);
    }

    public function recommendations(Request $request): JsonResponse
    {
        [$organization, $membership, $actor] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'interventions.manage'), 403);

        return response()->json(['data' => $this->dashboard->recommendations(
            $organization->getKey(),
            $actor,
            $this->permissions->allows($membership, 'interventions.view_organization'),
        )]);
    }

    /** @return array{Organization, Membership, User} */
    private function context(Request $request): array
    {
        $organization = $request->attributes->get('organization');
        $membership = $request->attributes->get('organization_membership');
        $actor = $request->user();
        abort_unless($organization instanceof Organization && $membership instanceof Membership && $actor instanceof User, 403);

        return [$organization, $membership, $actor];
    }
}
