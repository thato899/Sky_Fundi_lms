<?php

declare(strict_types=1);

namespace Modules\Staff\Application;

use Core\AuditLogs\Application\AuditLogService;
use Core\Identity\Application\MembershipService;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Staff\Infrastructure\Models\StaffProfile;

final class StaffService
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly MembershipService $memberships,
    ) {}

    public function create(Organization $organization, array $data, ?string $actorId): StaffProfile
    {
        return DB::transaction(function () use ($organization, $data, $actorId): StaffProfile {
            $user = User::query()->create(['email' => $data['email'], 'name' => trim($data['first_name'].' '.$data['last_name']), 'password' => Str::random(48), 'status' => 'active']);
            $role = Role::query()->firstOrCreate(['name' => 'Teacher'], ['description' => 'Organization teaching staff', 'is_system' => false]);
            $membership = $this->memberships->invite([
                'user_id' => $user->getKey(), 'organization_id' => $organization->getKey(), 'role_id' => $role->getKey(),
            ], $actorId);
            $staff = StaffProfile::query()->create([
                ...$this->profileData($data),
                'organization_id' => $organization->getKey(),
                'organization_membership_id' => $membership->getKey(),
                'user_id' => $user->getKey(),
            ]);
            $this->audit->record(action: 'staff.created', target: $staff, after: ['employee_number' => $staff->getAttribute('employee_number')]);

            return $staff->load(['user', 'membership', 'department']);
        });
    }

    public function update(StaffProfile $staff, array $data): StaffProfile
    {
        return DB::transaction(function () use ($staff, $data): StaffProfile {
            $staff->update($this->profileData($data));
            $staff->user()->update(['name' => trim($data['first_name'].' '.$data['last_name']), 'email' => $data['email']]);
            $this->audit->record(action: 'staff.updated', target: $staff, after: ['employee_number' => $staff->getAttribute('employee_number')]);

            return $staff->fresh(['user', 'membership', 'department']);
        });
    }

    public function transition(StaffProfile $staff, string $status): StaffProfile
    {
        if (in_array($staff->getAttribute('employment_status'), ['terminated', 'archived'], true) && $status === 'active') {
            throw new \DomainException('Archived or terminated staff cannot be activated.');
        }
        $staff->update(['employment_status' => $status, 'archived_at' => $status === 'archived' ? now() : null]);
        $this->audit->record(action: 'staff.status_changed', target: $staff, after: ['status' => $status]);

        return $staff->fresh();
    }

    private function profileData(array $data): array
    {
        return [
            'employee_number' => $data['employee_number'], 'title' => $data['title'] ?? null,
            'first_name' => $data['first_name'], 'last_name' => $data['last_name'],
            'work_email' => $data['email'], 'work_phone' => $data['phone'] ?? null,
            'staff_type' => $data['staff_type'], 'department_id' => $data['department_id'] ?? null,
            'employment_status' => $data['employment_status'],
            'portal_access_enabled' => $data['portal_access_enabled'] ?? false, 'notes' => $data['notes'] ?? null,
        ];
    }
}
