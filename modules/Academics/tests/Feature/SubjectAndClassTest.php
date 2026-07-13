<?php

declare(strict_types=1);

namespace Modules\Academics\Tests\Feature;

use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Infrastructure\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academics\Database\Seeders\AcademicsPermissionSeeder;
use Modules\Academics\Database\Seeders\DepartmentSeeder;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\Department;
use Modules\Academics\Infrastructure\Models\Grade;
use Tests\TestCase;

final class SubjectAndClassTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(AcademicsPermissionSeeder::class);

        $role = Role::create(['name' => 'Academics Admin', 'is_system' => false]);
        $role->permissions()->attach(Permission::query()->where('name', 'like', 'academics.%')->pluck('id'));

        $admin = User::factory()->create();
        $admin->roles()->attach($role->id);

        return $admin;
    }

    public function test_a_subject_can_be_created_and_assigned_a_department(): void
    {
        $admin = $this->actingAdmin();
        $this->seed(DepartmentSeeder::class);
        $department = Department::query()->where('code', 'MATH')->firstOrFail();

        $subject = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/academics/subjects', [
            'code' => 'MATH101',
            'name' => 'Mathematics',
        ])->assertCreated()->json('data');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/academics/subjects/{$subject['id']}/department", ['department_id' => $department->id])
            ->assertOk()
            ->assertJsonPath('data.department_id', $department->id);
    }

    public function test_a_class_requires_an_academic_year_and_a_grade(): void
    {
        $admin = $this->actingAdmin();

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/academics/classes', [
            'name' => '8A',
        ])->assertStatus(422)->assertJsonValidationErrors(['academic_year_id', 'grade_id']);
    }

    public function test_a_class_can_be_created_and_marked_as_a_homeroom(): void
    {
        $admin = $this->actingAdmin();
        $year = AcademicYear::create(['name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-01']);
        $grade = Grade::create(['name' => 'Grade 8', 'order' => 8]);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/academics/classes', [
            'name' => '8A',
            'capacity' => 30,
            'academic_year_id' => $year->id,
            'grade_id' => $grade->id,
            'is_homeroom' => true,
        ])->assertCreated()
            ->assertJsonPath('data.is_homeroom', true)
            ->assertJsonPath('data.capacity', 30);
    }
}
