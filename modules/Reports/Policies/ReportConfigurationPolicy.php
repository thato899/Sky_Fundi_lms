<?php

declare(strict_types=1);

namespace Modules\Reports\Policies;

use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Users\Infrastructure\Models\User;

final class ReportConfigurationPolicy
{
    public function __construct(private readonly PermissionResolver $permissions) {}

    public function viewAny(User $u): bool
    {
        return $this->allows($u, 'reports.view');
    }

    public function view(User $u, object $target): bool
    {
        return $this->owned($u, $target, 'reports.view');
    }

    public function manageGradingScales(User $u, ?object $target = null): bool
    {
        return $this->owned($u, $target, 'reports.manage_grading_scales');
    }

    public function managePeriods(User $u, ?object $target = null): bool
    {
        return $this->owned($u, $target, 'reports.manage_periods');
    }

    public function manageTemplates(User $u, ?object $target = null): bool
    {
        return $this->owned($u, $target, 'reports.manage_templates');
    }

    private function owned(User $u, ?object $target, string $permission): bool
    {
        $m = request()->attributes->get('organization_membership');

        return $m instanceof Membership && (! $target || $target->organization_id === $m->getAttribute('organization_id')) && $this->allows($u, $permission);
    }

    private function allows(User $u, string $permission): bool
    {
        $m = request()->attributes->get('organization_membership');

        return $m instanceof Membership && $m->getAttribute('user_id') === $u->getKey() && $this->permissions->allows($m, $permission);
    }
}
