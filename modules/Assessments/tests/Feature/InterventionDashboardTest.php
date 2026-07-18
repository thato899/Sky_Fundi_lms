<?php

declare(strict_types=1);

namespace Modules\Assessments\Tests\Feature;

use Core\RBAC\Infrastructure\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Modules\Assessments\Database\Seeders\AssessmentsPermissionSeeder;
use Tests\TestCase;

final class InterventionDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_intervention_dashboard_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('quizzes.interventions'));
        $this->assertContains('api/v1/interventions/dashboard', collect(Route::getRoutes())->map->uri()->all());
        $this->assertContains('api/v1/interventions/recommendations', collect(Route::getRoutes())->map->uri()->all());
    }

    public function test_teacher_permissions_are_scoped_below_organization_aggregate_access(): void
    {
        $this->seed(AssessmentsPermissionSeeder::class);

        $teacher = Role::query()->where('name', 'Teacher')->firstOrFail();
        $administrator = Role::query()->where('name', 'Organization Administrator')->firstOrFail();
        $learner = Role::query()->where('name', 'Learner')->firstOrFail();

        $this->assertTrue($teacher->permissions()->where('name', 'interventions.view')->exists());
        $this->assertTrue($teacher->permissions()->where('name', 'interventions.manage')->exists());
        $this->assertFalse($teacher->permissions()->where('name', 'interventions.view_organization')->exists());
        $this->assertTrue($administrator->permissions()->where('name', 'interventions.view_organization')->exists());
        $this->assertFalse($learner->permissions()->where('name', 'interventions.view')->exists());
    }
}
