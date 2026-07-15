<?php

declare(strict_types=1);

namespace Modules\Staff\Tests\Feature;

use Core\Identity\Infrastructure\Models\Membership;
use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academics\Infrastructure\Models\Department;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Staff\Infrastructure\Models\StaffProfile;
use Tests\TestCase;

final class StaffManagementWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_search_filters_sorting_and_pagination_are_organization_scoped(): void
    {
        [$admin, $organization, $membership] = $this->administrator();
        $department = Department::query()->create(['organization_id' => $organization->id, 'name' => 'Science', 'code' => 'SCI', 'is_active' => true]);
        $this->profile($organization, 'EMP-002', 'Zola', 'Smith', ['department_id' => $department->id, 'staff_type' => 'teacher', 'employment_status' => 'active', 'portal_access_enabled' => true]);
        $this->profile($organization, 'EMP-001', 'Amy', 'Jones', ['staff_type' => 'support', 'employment_status' => 'suspended']);
        [$otherAdmin, $other] = $this->administrator('Other Admin');
        $this->profile($other, 'PRIVATE-1', 'Private', 'Person');

        $base = $this->actingAs($admin)->withSession(['organization_id' => $membership->organization_id]);
        $base->get(route('staff.index'))->assertOk()->assertSee('EMP-001')->assertSee('EMP-002')->assertDontSee('PRIVATE-1');
        $base->get(route('staff.index', ['search' => 'Zola']))->assertSee('EMP-002')->assertDontSee('EMP-001');
        $base->get(route('staff.index', ['department_id' => $department->id, 'employment_status' => 'active', 'staff_type' => 'teacher', 'portal_access_enabled' => '1']))->assertSee('EMP-002')->assertDontSee('EMP-001');
        $sorted = $base->get(route('staff.index', ['sort' => 'employee_number', 'direction' => 'asc']))->getContent();
        $this->assertLessThan(strpos($sorted, 'EMP-002'), strpos($sorted, 'EMP-001'));

        foreach (range(3, 18) as $number) {
            $this->profile($organization, sprintf('EMP-%03d', $number), 'Staff', (string) $number);
        }
        $base->get(route('staff.index'))->assertSee('Page 1 of 2')->assertSee('Next');
    }

    public function test_create_edit_suspend_and_activate_reuse_staff_workflow(): void
    {
        [$admin, $organization, $membership] = $this->administrator();
        $department = Department::query()->create(['organization_id' => $organization->id, 'name' => 'Languages', 'code' => 'LAN', 'is_active' => true]);
        $session = $this->actingAs($admin)->withSession(['organization_id' => $membership->organization_id]);

        $session->get(route('staff.create'))->assertOk()->assertSee('Add staff member');
        $session->post(route('staff.store'), $this->payload(['department_id' => $department->id]))->assertRedirect();
        $profile = StaffProfile::query()->where('organization_id', $organization->id)->where('employee_number', 'EMP-100')->firstOrFail();
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.created', 'target_id' => $profile->id]);
        $session->get(route('staff.show', $profile))->assertOk()->assertSee('Ada Lovelace')->assertSee('Languages')->assertSee('Membership')->assertDontSee('before')->assertDontSee('user_agent');

        $session->put(route('staff.update', $profile), $this->payload(['last_name' => 'Byron', 'employee_number' => 'EMP-101']))->assertRedirect(route('staff.show', $profile));
        $this->assertDatabaseHas('staff_profiles', ['id' => $profile->id, 'last_name' => 'Byron', 'employee_number' => 'EMP-101']);
        $session->post(route('staff.suspend', $profile))->assertRedirect(route('staff.show', $profile));
        $this->assertSame('suspended', $profile->fresh()->employment_status);
        $session->post(route('staff.activate', $profile))->assertRedirect(route('staff.show', $profile));
        $this->assertSame('active', $profile->fresh()->employment_status);
    }

    public function test_permission_and_foreign_uuid_protection_are_enforced(): void
    {
        [$admin, $organization, $membership] = $this->administrator();
        [$otherAdmin, $other, $otherMembership] = $this->administrator('Other Organization Admin');
        $foreign = $this->profile($other, 'FOREIGN-1', 'Foreign', 'Staff');
        $this->actingAs($admin)->withSession(['organization_id' => $membership->organization_id])->get(route('staff.show', $foreign))->assertNotFound();
        auth()->logout();
        $this->get(route('staff.index'))->assertRedirect(route('login'));

        $teacher = User::factory()->create();
        $teacherRole = Role::query()->firstOrCreate(['name' => 'Tutor'], ['description' => 'Tutor', 'is_system' => false]);
        $teacherMembership = Membership::query()->create(['user_id' => $teacher->id, 'organization_id' => $organization->id, 'role_id' => $teacherRole->id, 'status' => 'active']);
        $this->actingAs($teacher)->withSession(['organization_id' => $teacherMembership->organization_id])->get(route('staff.index'))->assertForbidden();
    }

    public function test_validation_rejects_missing_fields_duplicates_and_foreign_departments(): void
    {
        [$admin, $organization, $membership] = $this->administrator();
        [$otherAdmin, $other] = $this->administrator('Other Validation Admin');
        $department = Department::query()->create(['organization_id' => $other->id, 'name' => 'Foreign', 'code' => 'FOR', 'is_active' => true]);
        $this->profile($organization, 'EMP-100', 'Existing', 'Staff');
        $session = $this->actingAs($admin)->withSession(['organization_id' => $membership->organization_id]);

        $session->from(route('staff.create'))->post(route('staff.store'), [])->assertSessionHasErrors(['employee_number', 'first_name', 'last_name', 'email', 'staff_type', 'employment_status']);
        $session->from(route('staff.create'))->post(route('staff.store'), $this->payload(['department_id' => $department->id]))->assertSessionHasErrors(['employee_number', 'department_id']);
        $this->assertDatabaseMissing('staff_profiles', ['organization_id' => $organization->id, 'first_name' => 'Ada']);
    }

    private function administrator(string $name = 'Organization Administrator'): array
    {
        $organization = Organization::query()->create(['name' => fake()->unique()->company(), 'code' => fake()->unique()->bothify('ORG-####'), 'type' => 'school', 'status' => 'active']);
        $organization->modules()->create(['module_name' => 'staff', 'enabled' => true]);
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(['name' => $name], ['description' => $name, 'is_system' => false]);
        foreach (['staff.view', 'staff.create', 'staff.update', 'staff.manage_employment'] as $name) {
            $permission = Permission::query()->firstOrCreate(['name' => $name], ['description' => $name, 'module' => 'staff']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $membership = Membership::query()->create(['user_id' => $user->id, 'organization_id' => $organization->id, 'role_id' => $role->id, 'status' => 'active']);

        return [$user, $organization, $membership];
    }

    private function profile(Organization $organization, string $number, string $first, string $last, array $extra = []): StaffProfile
    {
        $user = User::factory()->create(['name' => "{$first} {$last}"]);
        $role = Role::query()->firstOrCreate(['name' => 'Teacher'], ['description' => 'Teacher', 'is_system' => false]);
        $membership = Membership::query()->create(['user_id' => $user->id, 'organization_id' => $organization->id, 'role_id' => $role->id, 'status' => 'active']);

        return StaffProfile::query()->create([...['organization_id' => $organization->id, 'organization_membership_id' => $membership->id, 'user_id' => $user->id, 'employee_number' => $number, 'first_name' => $first, 'last_name' => $last, 'work_email' => strtolower($number).'@example.test', 'staff_type' => 'teacher', 'employment_status' => 'active'], ...$extra]);
    }

    private function payload(array $overrides = []): array
    {
        return [...['employee_number' => 'EMP-100', 'title' => 'Dr', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.test', 'phone' => '0123456789', 'staff_type' => 'teacher', 'employment_status' => 'active', 'portal_access_enabled' => '1', 'notes' => 'Mathematics lead'], ...$overrides];
    }
}
