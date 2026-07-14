<?php

declare(strict_types=1);

namespace Modules\Staff\Application;

use Core\AuditLogs\Application\AuditLogService;
use Modules\Staff\Infrastructure\Models\StaffProfile;

final class StaffService
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function create(array $data): StaffProfile
    {
        $staff = StaffProfile::create($data);
        $this->audit->record(action: 'staff.created', target: $staff, after: ['employee_number' => $staff->employee_number]);

        return $staff;
    }

    public function transition(StaffProfile $staff, string $status): StaffProfile
    {
        if (in_array($staff->employment_status, ['terminated', 'archived'], true) && $status === 'active') {
            throw new \DomainException('Archived or terminated staff cannot be activated.');
        }$staff->update(['employment_status' => $status, 'archived_at' => $status === 'archived' ? now() : null]);
        $this->audit->record(action: 'staff.status_changed', target: $staff, after: ['status' => $status]);

        return $staff->fresh();
    }
}
