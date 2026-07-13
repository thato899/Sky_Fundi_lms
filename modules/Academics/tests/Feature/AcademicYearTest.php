<?php

declare(strict_types=1);

namespace Modules\Academics\Tests\Feature;

use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Infrastructure\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academics\Database\Seeders\AcademicsPermissionSeeder;
use Modules\Academics\Domain\Enums\AcademicYearStatus;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Tests\TestCase;

final class AcademicYearTest extends TestCase
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

    public function test_an_authorised_user_can_create_an_academic_year(): void
    {
        $admin = $this->actingAdmin();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/academics/academic-years', [
            'name' => '2026 Academic Year',
            'start_date' => '2026-01-14',
            'end_date' => '2026-12-04',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', '2026 Academic Year')
            ->assertJsonPath('data.status', AcademicYearStatus::Upcoming->value);
    }

    public function test_creating_an_academic_year_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/academics/academic-years', [
            'name' => '2026 Academic Year',
            'start_date' => '2026-01-14',
            'end_date' => '2026-12-04',
        ])->assertStatus(403);
    }

    public function test_end_date_must_be_after_start_date(): void
    {
        $admin = $this->actingAdmin();

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/academics/academic-years', [
            'name' => 'Invalid Year',
            'start_date' => '2026-12-04',
            'end_date' => '2026-01-14',
        ])->assertStatus(422)->assertJsonValidationErrors(['end_date']);
    }

    public function test_setting_a_year_current_demotes_the_previous_current_year(): void
    {
        $admin = $this->actingAdmin();

        $yearOne = AcademicYear::create(['name' => '2025', 'start_date' => '2025-01-01', 'end_date' => '2025-12-01', 'is_current' => true, 'status' => AcademicYearStatus::Current]);
        $yearTwo = AcademicYear::create(['name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-01']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/academics/academic-years/{$yearTwo->id}/set-current")
            ->assertOk()
            ->assertJsonPath('data.is_current', true);

        $this->assertFalse($yearOne->fresh()->is_current);
        $this->assertSame(AcademicYearStatus::Closed, $yearOne->fresh()->status);
    }

    public function test_closing_a_year_removes_its_current_status(): void
    {
        $admin = $this->actingAdmin();
        $year = AcademicYear::create(['name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-01', 'is_current' => true, 'status' => AcademicYearStatus::Current]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/academics/academic-years/{$year->id}/close")
            ->assertOk()
            ->assertJsonPath('data.status', AcademicYearStatus::Closed->value);

        $this->assertFalse($year->fresh()->is_current);
    }
}
