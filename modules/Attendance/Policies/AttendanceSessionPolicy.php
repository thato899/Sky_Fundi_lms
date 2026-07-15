<?php

declare(strict_types=1);

namespace Modules\Attendance\Policies;

use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Users\Infrastructure\Models\User;
use Modules\Attendance\Domain\Enums\AttendanceSessionStatus;
use Modules\Attendance\Infrastructure\Models\AttendanceSession;

final class AttendanceSessionPolicy
{
    public function __construct(private readonly PermissionResolver $permissions) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'attendance.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'attendance.create');
    }

    public function view(User $user, AttendanceSession $session): bool
    {
        return $this->allows($user, 'attendance.view', $session);
    }

    public function record(User $user, AttendanceSession $session): bool
    {
        return $this->editable($session) && $this->allows($user, 'attendance.record', $session);
    }

    public function update(User $user, AttendanceSession $session): bool
    {
        return $this->editable($session) && $this->allows($user, 'attendance.update', $session);
    }

    public function finalize(User $user, AttendanceSession $session): bool
    {
        return $this->editable($session) && $this->allows($user, 'attendance.finalize', $session);
    }

    public function reopen(User $user, AttendanceSession $session): bool
    {
        return $session->getAttribute('status') === AttendanceSessionStatus::Finalized && $this->allows($user, 'attendance.reopen', $session);
    }

    public function cancel(User $user, AttendanceSession $session): bool
    {
        return $session->getAttribute('status') !== AttendanceSessionStatus::Finalized && $this->allows($user, 'attendance.cancel', $session);
    }

    public function export(User $user, AttendanceSession $session): bool
    {
        return $this->allows($user, 'attendance.export', $session);
    }

    public function viewReports(User $user): bool
    {
        return $this->allows($user, 'attendance.view_reports');
    }

    private function editable(AttendanceSession $session): bool
    {
        return in_array($session->getAttribute('status'), [AttendanceSessionStatus::Draft, AttendanceSessionStatus::Open], true);
    }

    private function allows(User $user, string $permission, ?AttendanceSession $session = null): bool
    {
        $membership = request()->attributes->get('organization_membership');
        if (! $membership instanceof Membership || $membership->getAttribute('user_id') !== $user->getKey()) {
            return false;
        }
        if ($session && $session->getAttribute('organization_id') !== $membership->getAttribute('organization_id')) {
            return false;
        }

        return $this->permissions->allows($membership, $permission);
    }
}
