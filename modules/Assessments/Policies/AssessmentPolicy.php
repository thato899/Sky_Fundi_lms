<?php

declare(strict_types=1);

namespace Modules\Assessments\Policies;

use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Users\Infrastructure\Models\User;
use Modules\Assessments\Domain\Enums\AssessmentStatus;
use Modules\Assessments\Infrastructure\Models\Assessment;

final class AssessmentPolicy
{
    public function __construct(private readonly PermissionResolver $permissions) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'assessments.view');
    }

    public function view(User $user, Assessment $assessment): bool
    {
        return $this->allows($user, 'assessments.view', $assessment);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'assessments.create');
    }

    public function update(User $user, Assessment $a): bool
    {
        return $this->editable($a) && $this->allows($user, 'assessments.update', $a);
    }

    public function mark(User $user, Assessment $a): bool
    {
        return $this->editable($a) && $this->allows($user, 'assessments.mark', $a);
    }

    public function finalize(User $user, Assessment $a): bool
    {
        return $this->editable($a) && $this->allows($user, 'assessments.finalize', $a);
    }

    public function reopen(User $user, Assessment $a): bool
    {
        return $a->getAttribute('status') === AssessmentStatus::Finalized && $this->allows($user, 'assessments.reopen', $a);
    }

    public function cancel(User $user, Assessment $a): bool
    {
        return $a->getAttribute('status') !== AssessmentStatus::Finalized && $this->allows($user, 'assessments.cancel', $a);
    }

    public function release(User $user, Assessment $a): bool
    {
        return $a->getAttribute('status') === AssessmentStatus::Finalized && $this->allows($user, 'assessments.release', $a);
    }

    public function export(User $user, Assessment $a): bool
    {
        return $this->allows($user, 'assessments.export', $a);
    }

    public function viewReports(User $user): bool
    {
        return $this->allows($user, 'assessments.view_reports');
    }

    private function editable(Assessment $a): bool
    {
        return in_array($a->getAttribute('status'), [AssessmentStatus::Draft, AssessmentStatus::Open], true);
    }

    private function allows(User $user, string $permission, ?Assessment $assessment = null): bool
    {
        $membership = request()->attributes->get('organization_membership');

        return $membership instanceof Membership && $membership->getAttribute('user_id') === $user->getKey() && (! $assessment || $assessment->getAttribute('organization_id') === $membership->getAttribute('organization_id')) && $this->permissions->allows($membership, $permission);
    }
}
