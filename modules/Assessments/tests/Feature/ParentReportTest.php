<?php

declare(strict_types=1);

namespace Modules\Assessments\Tests\Feature;

use Core\Users\Infrastructure\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\HackathonDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Assessments\Infrastructure\Models\QuizAttempt;
use Modules\Assessments\Infrastructure\Models\QuizStudyPlan;
use Modules\Learners\Infrastructure\Models\GuardianProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Tests\TestCase;

final class ParentReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_report_reaches_learner_and_guardian_and_is_blocked_for_learners(): void
    {
        config(['hackathon.demo_password' => 'TestDemoOnly-2026!']);
        $this->seed(DatabaseSeeder::class);
        $this->seed(HackathonDemoSeeder::class);

        $organization = Organization::query()->where('code', 'UFA-DEMO')->firstOrFail();
        $attempt = QuizAttempt::query()->where('organization_id', $organization->getKey())->whereNotNull('submitted_at')->firstOrFail();
        $plan = QuizStudyPlan::query()->where('quiz_attempt_id', $attempt->getKey())->firstOrFail();

        $teacher = User::query()->where('email', 'math.teacher@ubuntu-future.demo')->firstOrFail();
        $this->actingAs($teacher)->withSession(['organization_id' => $organization->getKey()])
            ->post(route('quizzes.study-plan.comment', [$attempt->uuid, $plan->uuid]), ['teacher_comment' => 'Lerato showed real growth this term — keep encouraging daily practice.'])
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->assertSame('Lerato showed real growth this term — keep encouraging daily practice.', $plan->refresh()->content['teacher_comment']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'study_plans.teacher_comment_updated']);

        $learner = User::query()->where('email', 'lerato@ubuntu-future.demo')->firstOrFail();
        $this->actingAs($learner)->withSession(['organization_id' => $organization->getKey()])
            ->get(route('quizzes.attempt', $attempt->uuid))
            ->assertOk()
            ->assertSee('Message from your teacher')
            ->assertSee('real growth this term');

        $guardianUser = User::query()->where('email', 'thandi@ubuntu-future.demo')->firstOrFail();
        $guardian = GuardianProfile::query()->where('user_id', $guardianUser->getKey())->firstOrFail();
        $this->actingAs($guardianUser)->withSession(['organization_id' => $organization->getKey()])
            ->get('/guardians/'.$guardian->uuid)
            ->assertOk()
            ->assertSee('real growth this term');

        $this->actingAs($learner)->withSession(['organization_id' => $organization->getKey()])
            ->post(route('quizzes.study-plan.comment', [$attempt->uuid, $plan->uuid]), ['teacher_comment' => 'nope'])
            ->assertForbidden();

        $empty = $this->actingAs($teacher)->withSession(['organization_id' => $organization->getKey()])
            ->from(route('quizzes.review', $attempt->uuid))
            ->post(route('quizzes.study-plan.comment', [$attempt->uuid, $plan->uuid]), ['teacher_comment' => '   ']);
        $empty->assertSessionHasErrors();
    }
}
