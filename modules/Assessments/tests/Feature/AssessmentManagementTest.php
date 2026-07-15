<?php

declare(strict_types=1);

namespace Modules\Assessments\Tests\Feature;

use Core\Identity\Infrastructure\Models\Membership;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academics\Infrastructure\Models\AcademicTerm;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Assessments\Application\AssessmentCategoryService;
use Modules\Assessments\Application\AssessmentResultService;
use Modules\Assessments\Application\AssessmentService;
use Modules\Assessments\Database\Seeders\AssessmentsPermissionSeeder;
use Modules\Assessments\Domain\Enums\AssessmentStatus;
use Modules\Assessments\Domain\Enums\ResultReleaseStatus;
use Modules\Assessments\Infrastructure\Models\Assessment;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Infrastructure\Models\OrganizationModule;
use Tests\TestCase;

final class AssessmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_populate_eligible_learners_record_atomically_and_calculate_percentages(): void
    {
        $c = $this->context('service');
        $eligible = LearnerProfile::factory()->create(['organization_id' => $c['organization']->id, 'current_academic_year_id' => $c['year']->id, 'current_grade_id' => $c['grade']->id, 'current_class_id' => $c['class']->id, 'learner_status' => 'active']);
        LearnerProfile::factory()->create(['organization_id' => $c['organization']->id, 'current_class_id' => $c['class']->id, 'learner_status' => 'withdrawn']);
        $assessment = $this->createAssessment($c);
        $this->assertCount(1, $assessment->results);
        $this->assertSame($eligible->id, $assessment->results->first()->learner_profile_id);
        $result = $assessment->results->first();
        app(AssessmentResultService::class)->record($assessment, $c['user'], [['result_uuid' => $result->uuid, 'result_status' => 'marked', 'score' => 37.5, 'feedback' => '=safe in app', 'private_note' => 'restricted']]);
        $this->assertDatabaseHas('assessment_results', ['id' => $result->id, 'score' => 37.50, 'percentage' => 75.00, 'result_status' => 'marked', 'marked_by' => $c['user']->id]);
    }

    public function test_invalid_complete_mark_sheet_rolls_back_every_row_and_non_marked_is_not_zero(): void
    {
        $c = $this->context('atomic');
        LearnerProfile::factory()->count(2)->create(['organization_id' => $c['organization']->id, 'current_class_id' => $c['class']->id, 'learner_status' => 'active']);
        $a = $this->createAssessment($c);
        $rows = $a->results->values();
        try {
            app(AssessmentResultService::class)->record($a, $c['user'], [['result_uuid' => $rows[0]->uuid, 'result_status' => 'marked', 'score' => 20], ['result_uuid' => $rows[1]->uuid, 'result_status' => 'marked', 'score' => 999]]);
            $this->fail('Invalid score accepted.');
        } catch (DomainException) {
        }
        $this->assertDatabaseCount('assessment_results', 2);
        $this->assertDatabaseMissing('assessment_results', ['result_status' => 'marked']);
        app(AssessmentResultService::class)->record($a, $c['user'], [['result_uuid' => $rows[0]->uuid, 'result_status' => 'absent', 'score' => 0], ['result_uuid' => $rows[1]->uuid, 'result_status' => 'exempt', 'score' => 0]]);
        $this->assertDatabaseHas('assessment_results', ['id' => $rows[0]->id, 'result_status' => 'absent', 'score' => null, 'percentage' => null]);
    }

    public function test_finalization_lock_reopen_and_separate_release_lifecycle(): void
    {
        $c = $this->context('lifecycle');
        LearnerProfile::factory()->create(['organization_id' => $c['organization']->id, 'current_class_id' => $c['class']->id, 'learner_status' => 'admitted']);
        $a = $this->createAssessment($c);
        $result = $a->results->first();
        app(AssessmentResultService::class)->record($a, $c['user'], [['result_uuid' => $result->uuid, 'result_status' => 'not_submitted']]);
        $service = app(AssessmentService::class);
        $service->finalize($a->refresh(), $c['user']);
        $this->assertSame(AssessmentStatus::Finalized, $a->refresh()->status);
        $this->assertSame(ResultReleaseStatus::Withheld, $a->result_release_status);
        try {
            app(AssessmentResultService::class)->record($a, $c['user'], [['result_uuid' => $result->uuid, 'result_status' => 'marked', 'score' => 1]]);
            $this->fail('Finalized mark changed.');
        } catch (DomainException) {
        }
        $service->release($a->refresh(), $c['user'], true);
        $this->assertSame(ResultReleaseStatus::Released, $a->refresh()->result_release_status);
        $service->release($a->refresh(), $c['user'], false);
        $this->assertSame(ResultReleaseStatus::Withheld, $a->refresh()->result_release_status);
        $service->reopen($a->refresh(), $c['user'], 'Correction required');
        $this->assertSame(AssessmentStatus::Open, $a->refresh()->status);
    }

    public function test_web_api_gradebook_history_reports_export_and_private_note_boundary(): void
    {
        $c = $this->context('http');
        $learner = LearnerProfile::factory()->create(['organization_id' => $c['organization']->id, 'current_class_id' => $c['class']->id, 'learner_status' => 'active']);
        $a = $this->createAssessment($c);
        $r = $a->results->first();
        app(AssessmentResultService::class)->record($a, $c['user'], [['result_uuid' => $r->uuid, 'result_status' => 'marked', 'score' => 40, 'feedback' => '=formula', 'private_note' => 'never expose']]);
        $web = $this->actingAs($c['user'])->withSession(['organization_id' => $c['organization']->id]);
        $web->get('/assessments')->assertOk()->assertSee('Assessment management');
        $web->get('/gradebook')->assertOk()->assertSee($learner->learner_number);
        $web->get('/learners/'.$learner->uuid.'/results')->assertOk()->assertDontSee('never expose');
        $web->get('/assessment-reports')->assertOk()->assertSee('80.00%');
        $csv = $web->get('/assessments/'.$a->uuid.'/export')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringNotContainsString('never expose', $csv->streamedContent());
        $this->assertStringContainsString("'=formula", $csv->streamedContent());
        $headers = ['X-Organization-Id' => $c['organization']->id];
        $this->actingAs($c['user'], 'sanctum')->withHeaders($headers)->getJson('/api/v1/assessments/'.$a->uuid)->assertOk()->assertJsonMissing(['private_note' => 'never expose'])->assertJsonPath('data.results.0.percentage', '80.00');
        $this->withHeaders($headers)->getJson('/api/v1/gradebook')->assertOk();
        $this->withHeaders($headers)->getJson('/api/v1/assessment-reports/summary')->assertOk()->assertJsonPath('marked_result_count', 1);
    }

    public function test_foreign_uuid_and_references_are_rejected_and_mutations_are_not_get(): void
    {
        $a = $this->context('secure-a');
        $b = $this->context('secure-b');
        LearnerProfile::factory()->create(['organization_id' => $b['organization']->id, 'current_class_id' => $b['class']->id, 'learner_status' => 'active']);
        $foreign = $this->createAssessment($b);
        $web = $this->actingAs($a['user'])->withSession(['organization_id' => $a['organization']->id]);
        $web->get('/assessments/'.$foreign->uuid)->assertNotFound();
        $web->get('/assessments/'.$foreign->uuid.'/finalize')->assertStatus(405);
        $web->post('/assessments', $this->data($a, ['subject_id' => $b['subject']->id]))->assertSessionHasErrors();
        auth()->logout();
        $this->get('/assessments')->assertRedirect('/login');
    }

    private function createAssessment(array $c): Assessment
    {
        return app(AssessmentService::class)->create($c['organization'], $c['user'], $this->data($c));
    }

    private function data(array $c, array $override = []): array
    {
        return [...['academic_year_id' => $c['year']->id, 'academic_term_id' => $c['term']->id, 'grade_id' => $c['grade']->id, 'class_id' => $c['class']->id, 'subject_id' => $c['subject']->id, 'assessment_category_id' => $c['category']->id, 'title' => 'Test '.$c['organization']->code, 'assessment_date' => '2026-07-15', 'due_date' => '2026-07-16', 'maximum_mark' => 50, 'weighting' => 25], ...$override];
    }

    private function context(string $code): array
    {
        $this->seed(AssessmentsPermissionSeeder::class);
        $o = Organization::query()->create(['name' => $code, 'code' => $code, 'type' => 'school']);
        foreach (['assessments', 'academics', 'learners', 'staff'] as $module) {
            OrganizationModule::query()->create(['organization_id' => $o->id, 'module_name' => $module, 'enabled' => true]);
        } $u = User::factory()->create();
        $role = Role::query()->where('name', 'Organization Administrator')->firstOrFail();
        $m = Membership::query()->create(['organization_id' => $o->id, 'user_id' => $u->id, 'role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
        $year = AcademicYear::query()->create(['organization_id' => $o->id, 'name' => '2026 '.$code, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $term = AcademicTerm::query()->create(['organization_id' => $o->id, 'academic_year_id' => $year->id, 'term_number' => 1, 'name' => 'Term 1', 'start_date' => '2026-01-01', 'end_date' => '2026-03-31']);
        $grade = Grade::query()->create(['organization_id' => $o->id, 'academic_year_id' => $year->id, 'name' => 'Grade '.$code, 'order' => 1]);
        $class = ClassGroup::query()->create(['organization_id' => $o->id, 'academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => 'Class '.$code]);
        $subject = Subject::query()->create(['organization_id' => $o->id, 'name' => 'Mathematics '.$code, 'code' => 'M'.$code]);
        $this->actingAs($u)->withSession(['organization_id' => $o->id]);
        $category = app(AssessmentCategoryService::class)->create($o, $u, ['name' => 'Test', 'code' => 'TEST']);

        return ['organization' => $o, 'user' => $u, 'membership' => $m, 'year' => $year, 'term' => $term, 'grade' => $grade, 'class' => $class, 'subject' => $subject, 'category' => $category];
    }
}
