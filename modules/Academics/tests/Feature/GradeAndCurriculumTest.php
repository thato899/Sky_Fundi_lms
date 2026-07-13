<?php

declare(strict_types=1);

namespace Modules\Academics\Tests\Feature;

use Core\RBAC\Infrastructure\Models\Permission;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Infrastructure\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academics\Database\Seeders\AcademicsPermissionSeeder;
use Modules\Academics\Database\Seeders\CurriculumSeeder;
use Modules\Academics\Infrastructure\Models\Curriculum;
use Modules\Academics\Infrastructure\Models\Grade;
use Tests\TestCase;

final class GradeAndCurriculumTest extends TestCase
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

    public function test_curricula_are_seeded_from_the_database_not_hardcoded(): void
    {
        $this->seed(CurriculumSeeder::class);

        $this->assertDatabaseHas('academics_curricula', ['code' => 'CAPS']);
        $this->assertDatabaseHas('academics_curricula', ['code' => 'CUSTOM']);
        $this->assertSame(4, Curriculum::query()->count());
    }

    public function test_a_grade_can_be_created_and_a_curriculum_assigned(): void
    {
        $admin = $this->actingAdmin();
        $this->seed(CurriculumSeeder::class);
        $curriculum = Curriculum::query()->where('code', 'CAPS')->firstOrFail();

        $created = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/academics/grades', [
            'name' => 'Grade 8',
            'order' => 8,
        ])->assertCreated()->json('data');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/academics/grades/{$created['id']}/curriculum", ['curriculum_id' => $curriculum->id])
            ->assertOk()
            ->assertJsonPath('data.curriculum_id', $curriculum->id);
    }

    public function test_grades_can_be_reordered(): void
    {
        $admin = $this->actingAdmin();

        $a = Grade::create(['name' => 'Grade 8', 'order' => 1]);
        $b = Grade::create(['name' => 'Grade 9', 'order' => 2]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/academics/grades/reorder', ['grade_ids' => [$b->id, $a->id]])
            ->assertOk();

        $this->assertSame(1, $b->fresh()->order);
        $this->assertSame(2, $a->fresh()->order);
    }
}
