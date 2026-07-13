<?php

declare(strict_types=1);

namespace Modules\Academics\Tests\Feature;

use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Infrastructure\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academics\Database\Seeders\AcademicsPermissionSeeder;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Tests\TestCase;

final class AcademicCalendarTest extends TestCase
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

    public function test_a_calendar_entry_can_be_added_to_an_academic_year(): void
    {
        $admin = $this->actingAdmin();
        $year = AcademicYear::create(['name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-01']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/academics/academic-years/{$year->id}/calendar-entries", [
                'type' => 'public_holiday',
                'name' => 'Human Rights Day',
                'start_date' => '2026-03-21',
                'end_date' => '2026-03-21',
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'public_holiday');
    }

    public function test_calendar_entries_can_be_filtered_by_type(): void
    {
        $admin = $this->actingAdmin();
        $year = AcademicYear::create(['name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-01']);

        $year->calendarEntries()->create(['type' => 'public_holiday', 'name' => 'Holiday', 'start_date' => '2026-03-21', 'end_date' => '2026-03-21']);
        $year->calendarEntries()->create(['type' => 'exam_period', 'name' => 'Midyear Exams', 'start_date' => '2026-06-01', 'end_date' => '2026-06-14']);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/academics/academic-years/{$year->id}/calendar-entries?type=exam_period")
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('exam_period', $response->json('data.0.type'));
    }
}
