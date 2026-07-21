<?php

declare(strict_types=1);

namespace Modules\Reports\Tests\Feature;

use Core\Identity\Infrastructure\Models\Membership;
use Core\Notifications\Infrastructure\Notifications\CoreNotification;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Academics\Infrastructure\Models\AcademicTerm;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Assessments\Application\AssessmentCategoryService;
use Modules\Assessments\Application\AssessmentResultService;
use Modules\Assessments\Application\AssessmentService;
use Modules\Assessments\Database\Seeders\AssessmentsPermissionSeeder;
use Modules\Learners\Infrastructure\Models\GuardianProfile;
use Modules\Learners\Infrastructure\Models\LearnerGuardianRelationship;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Infrastructure\Models\OrganizationModule;
use Modules\Reports\Application\ReportCardService;
use Modules\Reports\Application\ReportConfigurationService;
use Modules\Reports\Database\Seeders\ReportsPermissionSeeder;
use Modules\Reports\Domain\Enums\ReportCardStatus;
use Modules\Reports\Domain\Enums\ReportingPeriodStatus;
use Tests\TestCase;

final class LearnerReportPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_publication_notifies_learner_and_guardian_and_portal_shows_only_published_cards(): void
    {
        Notification::fake();
        $c = $this->context('portal');
        $learnerUser = User::factory()->create();
        $learnerRole = Role::query()->firstOrCreate(['name' => 'Learner'], ['is_system' => false]);
        Membership::query()->create(['organization_id' => $c['organization']->id, 'user_id' => $learnerUser->id, 'role_id' => $learnerRole->id, 'status' => 'active']);
        $c['learner']->update(['user_id' => $learnerUser->id, 'portal_access_enabled' => true]);

        $guardianUser = User::factory()->create();
        $guardian = GuardianProfile::query()->create(['organization_id' => $c['organization']->id, 'user_id' => $guardianUser->id, 'first_name' => 'Guardian', 'last_name' => 'Portal', 'email' => 'guardian.portal@example.test', 'status' => 'active']);
        LearnerGuardianRelationship::query()->create(['organization_id' => $c['organization']->id, 'learner_profile_id' => $c['learner']->id, 'guardian_profile_id' => $guardian->id, 'relationship_type' => 'mother', 'is_primary' => true, 'receives_academic_communication' => true, 'status' => 'active']);

        $scale = $this->scale($c);
        $period = $this->period($c);
        $template = $this->template($c);
        $this->assessment($c, 80, '2026-02-01');
        $card = app(ReportCardService::class)->generate($c['learner'], $period, $scale, $template, $c['user']);

        $portal = fn () => $this->actingAs($learnerUser)->withSession(['organization_id' => $c['organization']->id])->get(route('reports.my'));
        $portal()->assertOk()->assertSee('No published report cards yet');
        Notification::assertNothingSent();

        $service = app(ReportCardService::class);
        $service->transition($card, $c['user'], ReportCardStatus::UnderReview);
        $service->transition($card->refresh(), $c['user'], ReportCardStatus::Approved);
        $service->transition($card->refresh(), $c['user'], ReportCardStatus::Published);

        Notification::assertSentTo($learnerUser, CoreNotification::class);
        Notification::assertSentTo($guardianUser, CoreNotification::class);

        $portal()->assertOk()
            ->assertSee($period->name)
            ->assertSee('Mathematics portal')
            ->assertSee('Excellent')
            ->assertSee('80.0');

        $staffOnly = $this->actingAs($c['user'])->withSession(['organization_id' => $c['organization']->id])->get(route('reports.my'));
        $staffOnly->assertNotFound();
    }

    private function scale(array $c)
    {
        return app(ReportConfigurationService::class)->saveScale($c['organization'], $c['user'], ['name' => 'Scale '.$c['organization']->code, 'is_active' => true, 'bands' => [['label' => 'Needs support', 'minimum_percentage' => 0, 'maximum_percentage' => 49.99, 'symbol' => 'N'], ['label' => 'Achieved', 'minimum_percentage' => 50, 'maximum_percentage' => 74.99, 'symbol' => 'A'], ['label' => 'Excellent', 'minimum_percentage' => 75, 'maximum_percentage' => 100, 'symbol' => 'E']]]);
    }

    private function period(array $c)
    {
        $p = app(ReportConfigurationService::class)->savePeriod($c['organization'], $c['user'], ['academic_year_id' => $c['year']->id, 'academic_term_id' => $c['term']->id, 'name' => 'Term report '.$c['organization']->code, 'code' => 'T1', 'start_date' => '2026-01-01', 'end_date' => '2026-03-31', 'result_cutoff_date' => '2026-03-31']);

        return app(ReportConfigurationService::class)->transitionPeriod($p, $c['user'], ReportingPeriodStatus::Open);
    }

    private function template(array $c)
    {
        return app(ReportConfigurationService::class)->saveTemplate($c['organization'], $c['user'], ['name' => 'Standard '.$c['organization']->code, 'is_active' => true, 'show_attendance' => true, 'show_assessment_breakdown' => true, 'show_subject_comments' => true, 'show_overall_comment' => true, 'show_grading_legend' => true, 'show_organization_logo' => true, 'page_size' => 'A4']);
    }

    private function assessment(array $c, float $percentage, string $date): void
    {
        $a = app(AssessmentService::class)->create($c['organization'], $c['user'], ['academic_year_id' => $c['year']->id, 'academic_term_id' => $c['term']->id, 'grade_id' => $c['grade']->id, 'class_id' => $c['class']->id, 'subject_id' => $c['subject']->id, 'assessment_category_id' => $c['category']->id, 'title' => 'Assessment '.$date, 'assessment_date' => $date, 'maximum_mark' => 100]);
        $result = $a->results->first();
        app(AssessmentResultService::class)->record($a, $c['user'], [['result_uuid' => $result->uuid, 'result_status' => 'marked', 'score' => $percentage]]);
        app(AssessmentService::class)->finalize($a->refresh(), $c['user']);
    }

    private function context(string $code): array
    {
        $this->seed([AssessmentsPermissionSeeder::class, ReportsPermissionSeeder::class]);
        $o = Organization::query()->create(['name' => $code, 'code' => $code, 'type' => 'school']);
        foreach (['academics', 'learners', 'staff', 'attendance', 'assessments', 'reports'] as $module) {
            OrganizationModule::query()->create(['organization_id' => $o->id, 'module_name' => $module, 'enabled' => true]);
        }
        $u = User::factory()->create();
        $role = Role::query()->where('name', 'Organization Administrator')->firstOrFail();
        Membership::query()->create(['organization_id' => $o->id, 'user_id' => $u->id, 'role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
        $year = AcademicYear::query()->create(['organization_id' => $o->id, 'name' => '2026 '.$code, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $term = AcademicTerm::query()->create(['organization_id' => $o->id, 'academic_year_id' => $year->id, 'term_number' => 1, 'name' => 'Term 1', 'start_date' => '2026-01-01', 'end_date' => '2026-03-31']);
        $grade = Grade::query()->create(['organization_id' => $o->id, 'academic_year_id' => $year->id, 'name' => 'Grade '.$code, 'order' => 1]);
        $class = ClassGroup::query()->create(['organization_id' => $o->id, 'academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => 'Class '.$code]);
        $subject = Subject::query()->create(['organization_id' => $o->id, 'name' => 'Mathematics '.$code, 'code' => 'M'.$code]);
        $learner = LearnerProfile::factory()->create(['organization_id' => $o->id, 'current_academic_year_id' => $year->id, 'current_grade_id' => $grade->id, 'current_class_id' => $class->id, 'learner_status' => 'active']);
        $this->actingAs($u)->withSession(['organization_id' => $o->id]);
        $category = app(AssessmentCategoryService::class)->create($o, $u, ['name' => 'Test '.$code]);

        return ['organization' => $o, 'user' => $u, 'year' => $year, 'term' => $term, 'grade' => $grade, 'class' => $class, 'subject' => $subject, 'learner' => $learner, 'category' => $category];
    }
}
