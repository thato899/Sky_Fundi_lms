<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use Core\Users\Infrastructure\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\HackathonDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Assessments\Infrastructure\Models\Assessment;
use Modules\Assessments\Infrastructure\Models\QuizAttempt;
use Modules\Learners\Application\GuardianPortalAccessService;
use Modules\Learners\Infrastructure\Models\GuardianProfile;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Tests\TestCase;

final class HackathonDemoJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_is_idempotent_and_persona_views_render_safe_connected_story(): void
    {
        config(['hackathon.demo_password' => 'TestDemoOnly-2026!']);
        $this->seed(DatabaseSeeder::class);
        $this->seed(HackathonDemoSeeder::class);
        $this->seed(HackathonDemoSeeder::class);

        $organization = Organization::query()->where('code', 'UFA-DEMO')->firstOrFail();
        $this->assertSame('Demo School', $organization->name);
        $this->assertDatabaseHas('users', ['email' => 'tutor@ubuntu-future.demo']);
        $quiz = Assessment::query()->where('organization_id', $organization->getKey())->where('title', 'Forces and Linear Equations Check-in')->firstOrFail();
        $attempt = QuizAttempt::query()->where('assessment_id', $quiz->getKey())->firstOrFail();

        $admin = User::query()->where('email', 'admin@ubuntu-future.demo')->firstOrFail();
        $this->actingAs($admin)->withSession(['organization_id' => $organization->getKey()])
            ->get(route('subscription.dashboard'))
            ->assertOk()
            ->assertSee('Growth')
            ->assertSee('Demo billing')
            ->assertSee('Contribution margin');

        $teacher = User::query()->where('email', 'math.teacher@ubuntu-future.demo')->firstOrFail();
        $this->actingAs($teacher)->withSession(['organization_id' => $organization->getKey()])
            ->get(route('quizzes.show', $quiz->uuid))
            ->assertOk()
            ->assertSee('Teacher quiz workspace')
            ->assertSee('Solve 2x + 3 = 11');
        $this->get(route('quizzes.review', $attempt->uuid))
            ->assertOk()
            ->assertSee('Teacher oversight')
            ->assertSee('Show the substitution step');

        $learner = User::query()->where('email', 'lerato@ubuntu-future.demo')->firstOrFail();
        $this->actingAs($learner)->withSession(['organization_id' => $organization->getKey()])
            ->get(route('quizzes.attempt', $attempt->uuid))
            ->assertOk()
            ->assertSee('Released result')
            ->assertSee('Your personalized study plan')
            ->assertSee('Targeted revision exercises')
            ->assertDontSee('confidence');

        $guardian = User::query()->where('email', 'thandi@ubuntu-future.demo')->firstOrFail();
        $guardianProfile = GuardianProfile::query()->where('user_id', $guardian->getKey())->firstOrFail();
        $lerato = LearnerProfile::query()->where('learner_number', 'UFA-2026-001')->firstOrFail();
        $this->assertTrue(app(GuardianPortalAccessService::class)->allows($guardian, $lerato));
        $this->actingAs($guardian)->withSession(['organization_id' => $organization->getKey()])
            ->get(route('guardians.show', $guardianProfile->uuid))
            ->assertOk()
            ->assertSee('Latest released score')
            ->assertSee('Overall study progress')
            ->assertSee('Lerato Molefe')
            ->assertDontSee('Amo Khumalo')
            ->assertDontSee('residential_address')
            ->assertDontSee('learner answer');

        $this->assertDatabaseCount('organizations', 1);
        $this->assertDatabaseCount('quiz_attempts', 1);
        $this->assertDatabaseCount('ai_grading_requests', 1);
    }
}
