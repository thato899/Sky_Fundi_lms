<?php

declare(strict_types=1);

namespace Modules\Scheduling\Policies;

use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Model;

final class SchedulingPolicy
{
    public function __construct(private readonly PermissionResolver $permissions) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'scheduling.view');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->allows($user, 'scheduling.view', $model);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'scheduling.manage_lessons');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->allows($user, 'scheduling.manage_lessons', $model) && $model->getAttribute('status') !== 'completed';
    }

    public function cancel(User $user, Model $model): bool
    {
        return $this->allows($user, 'scheduling.cancel', $model);
    }

    public function reschedule(User $user, Model $model): bool
    {
        return $this->allows($user, 'scheduling.reschedule', $model);
    }

    public function complete(User $user, Model $model): bool
    {
        return $this->allows($user, 'scheduling.complete', $model);
    }

    public function assignStaff(User $user, Model $model): bool
    {
        return $this->allows($user, 'scheduling.assign_staff', $model);
    }

    public function materialize(User $user, Model $model): bool
    {
        return $this->allows($user, 'scheduling.materialize', $model);
    }

    public function export(User $user): bool
    {
        return $this->allows($user, 'scheduling.export');
    }

    public function createAttendance(User $user, Model $model): bool
    {
        return $this->allows($user, 'scheduling.create_attendance', $model);
    }

    public function overrideConflict(User $user): bool
    {
        return $this->allows($user, 'scheduling.override_conflicts');
    }

    private function allows(User $user, string $permission, ?Model $resource = null): bool
    {
        $membership = request()->attributes->get('organization_membership');

        return $membership instanceof Membership && $membership->user_id === $user->getKey() && (! $resource || $resource->getAttribute('organization_id') === $membership->organization_id) && $this->permissions->allows($membership, $permission);
    }
}
