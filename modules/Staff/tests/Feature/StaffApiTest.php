<?php

declare(strict_types=1);

namespace Modules\Staff\Tests\Feature;

use Core\Identity\Infrastructure\Models\Membership;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Infrastructure\Models\OrganizationModule;
use Modules\Staff\Database\Seeders\StaffPermissionSeeder;
use Modules\Staff\Infrastructure\Models\StaffProfile;
use Tests\TestCase;

/**
 * Regression coverage for a real, previously-undetected bug: these routes
 * used the platform-wide `permission:` middleware (Core\RBAC, direct
 * user-to-role assignment) instead of PermissionResolver (organization
 * Membership roles) — the same authorization model every other module's
 * API uses. An ordinary org-scoped "Organization Administrator", granted
 * exactly the way every other module in this codebase grants permissions,
 * could not call any of these endpoints before the fix. Nothing previously
 * exercised /api/v1/staff/* over HTTP with a realistic org-scoped
 * membership, which is why it went unnoticed.
 */
final class StaffApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_org_scoped_administrator_can_use_the_full_staff_api(): void
    {
        [$organization, $admin] = $this->member('api-staff', 'Organization Administrator');
        $headers = ['X-Organization-Id' => $organization->id];

        $created = $this->actingAs($admin, 'sanctum')->withHeaders($headers)->postJson('/api/v1/staff', [
            'employee_number' => 'EMP-API-1', 'first_name' => 'Api', 'last_name' => 'Staff',
            'email' => 'api.staff@example.test', 'staff_type' => 'teacher', 'employment_status' => 'active',
        ])->assertCreated()->assertJsonPath('data.employee_number', 'EMP-API-1');
        $staffId = $created->json('data.id');

        $this->withHeaders($headers)->getJson('/api/v1/staff')->assertOk()->assertJsonCount(1, 'data');

        $this->withHeaders($headers)->patchJson("/api/v1/staff/{$staffId}", [
            'employee_number' => 'EMP-API-1', 'first_name' => 'Api', 'last_name' => 'Updated',
            'email' => 'api.staff@example.test', 'staff_type' => 'teacher', 'employment_status' => 'active',
        ])->assertOk()->assertJsonPath('data.last_name', 'Updated');

        $this->withHeaders($headers)->postJson("/api/v1/staff/{$staffId}/suspend")
            ->assertOk()->assertJsonPath('data.employment_status', 'suspend');
        $this->withHeaders($headers)->postJson("/api/v1/staff/{$staffId}/activate")
            ->assertOk()->assertJsonPath('data.employment_status', 'activate');
    }

    public function test_org_scoped_administrator_can_use_per_staff_teaching_assignment_api(): void
    {
        [$organization, $admin] = $this->member('api-assignments', 'Organization Administrator');
        $headers = ['X-Organization-Id' => $organization->id];
        $staffUser = User::factory()->create();
        $staffMembership = Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $staffUser->id, 'status' => 'active']);
        $staff = StaffProfile::query()->create([
            'organization_id' => $organization->id, 'organization_membership_id' => $staffMembership->id, 'user_id' => $staffUser->id,
            'employee_number' => 'EMP-TA-1', 'first_name' => 'Assign', 'last_name' => 'Ment', 'staff_type' => 'teacher', 'employment_status' => 'active',
        ]);
        $year = AcademicYear::query()->create(['organization_id' => $organization->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'current']);
        $grade = Grade::query()->create(['organization_id' => $organization->id, 'name' => 'Grade', 'order' => 1, 'academic_year_id' => $year->id]);
        $class = ClassGroup::query()->create(['organization_id' => $organization->id, 'name' => 'Class', 'academic_year_id' => $year->id, 'grade_id' => $grade->id]);

        $this->actingAs($admin, 'sanctum')->withHeaders($headers)
            ->getJson("/api/v1/staff/{$staff->id}/teaching-assignments")->assertOk()->assertJsonCount(0, 'data');

        $created = $this->withHeaders($headers)->postJson("/api/v1/staff/{$staff->id}/teaching-assignments", [
            'class_id' => $class->id, 'academic_year_id' => $year->id,
        ])->assertCreated();
        $assignmentId = $created->json('data.id');

        $this->withHeaders($headers)->postJson("/api/v1/staff/{$staff->id}/teaching-assignments/{$assignmentId}/end")
            ->assertOk()->assertJsonPath('data.is_active', false);
    }

    public function test_membership_without_the_permission_is_forbidden_not_silently_allowed(): void
    {
        [$organization, $admin] = $this->member('api-negative', 'Organization Administrator');
        $headers = ['X-Organization-Id' => $organization->id];
        $teacherRole = Role::query()->firstOrCreate(['name' => 'Teacher'], ['is_system' => false]);
        $teacher = User::factory()->create();
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $teacher->id, 'role_id' => $teacherRole->id, 'status' => 'active', 'is_default' => true]);

        $this->actingAs($teacher, 'sanctum')->withHeaders($headers)->getJson('/api/v1/staff')->assertForbidden();
        $this->actingAs($teacher, 'sanctum')->withHeaders($headers)->postJson('/api/v1/staff', [
            'employee_number' => 'EMP-NEG', 'first_name' => 'No', 'last_name' => 'Access',
            'email' => 'no.access@example.test', 'staff_type' => 'teacher', 'employment_status' => 'active',
        ])->assertForbidden();

        // The admin path still works — proves the negative case above is a
        // real permission check, not a broken route.
        $this->actingAs($admin, 'sanctum')->withHeaders($headers)->getJson('/api/v1/staff')->assertOk();
    }

    /** @return array{Organization, User} */
    private function member(string $code, string $roleName): array
    {
        $this->seed(StaffPermissionSeeder::class);
        $organization = Organization::query()->create(['name' => $code, 'code' => $code, 'type' => 'school']);
        OrganizationModule::query()->create(['organization_id' => $organization->id, 'module_name' => 'staff', 'enabled' => true]);
        $user = User::factory()->create();
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active', 'is_default' => true]);

        return [$organization, $user];
    }
}
