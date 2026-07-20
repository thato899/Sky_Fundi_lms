<?php

declare(strict_types=1);

namespace Modules\Reports\Tests\Feature;

use Core\Identity\Infrastructure\Models\Membership;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Academics\Infrastructure\Models\AcademicTerm;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Assessments\Application\AssessmentCategoryService;
use Modules\Assessments\Application\AssessmentResultService;
use Modules\Assessments\Application\AssessmentService;
use Modules\Assessments\Database\Seeders\AssessmentsPermissionSeeder;
use Modules\Assessments\Infrastructure\Models\Assessment;
use Modules\Attendance\Infrastructure\Models\AttendanceEntry;
use Modules\Attendance\Infrastructure\Models\AttendanceSession;
use Modules\Learners\Application\LearnerService;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Infrastructure\Models\OrganizationModule;
use Modules\Reports\Application\ReportCardCalculationService;
use Modules\Reports\Application\ReportCardService;
use Modules\Reports\Application\ReportConfigurationService;
use Modules\Reports\Database\Seeders\ReportsPermissionSeeder;
use Modules\Reports\Domain\Enums\ReportCardStatus;
use Modules\Reports\Domain\Enums\ReportingPeriodStatus;
use Modules\Reports\Domain\Enums\SubjectResultStatus;
use Modules\Reports\Infrastructure\Models\GradingScale;
use Modules\Reports\Infrastructure\Models\ReportCardTemplate;
use Modules\Reports\Infrastructure\Models\ReportingPeriod;
use Tests\TestCase;

final class ReportManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_contains_report_schema_and_version_constraint(): void
    {
        foreach (['grading_scales', 'grading_scale_bands', 'reporting_periods', 'report_card_templates', 'report_cards', 'report_card_subject_results', 'report_card_comments'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $this->assertTrue(Schema::hasColumns('report_cards', ['organization_id', 'learner_profile_id', 'reporting_period_id', 'version_number', 'status', 'snapshot_metadata']));
    }

    public function test_grading_bands_periods_and_templates_validate_and_transition(): void
    {
        $c = $this->context('config');
        $service = app(ReportConfigurationService::class);
        try {
            $service->saveScale($c['organization'], $c['user'], ['name' => 'Bad', 'bands' => [['label' => 'A', 'minimum_percentage' => 0, 'maximum_percentage' => 60], ['label' => 'B', 'minimum_percentage' => 50, 'maximum_percentage' => 100]]]);
            $this->fail('Overlap accepted.');
        } catch (DomainException) {
        }
        $scale = $this->scale($c);
        $service->setScaleState($scale, $c['user'], true, true);
        $this->assertTrue($scale->refresh()->is_default);
        $period = $this->period($c);
        $service->transitionPeriod($period, $c['user'], ReportingPeriodStatus::Open);
        $service->transitionPeriod($period->refresh(), $c['user'], ReportingPeriodStatus::Closed);
        $this->assertSame(ReportingPeriodStatus::Closed, $period->refresh()->status);
        try {
            $service->savePeriod($c['organization'], $c['user'], ['academic_year_id' => $c['year']->id, 'name' => 'Changed', 'start_date' => '2026-01-01', 'end_date' => '2026-01-31'], $period);
            $this->fail('Closed period edited.');
        } catch (DomainException) {
        }
        $template = $this->template($c);
        $service->defaultTemplate($template, $c['user']);
        $this->assertTrue($template->refresh()->is_default);
    }

    public function test_calculation_uses_finalized_marked_results_weighting_bands_and_finalized_attendance_only(): void
    {
        $c = $this->context('calc');
        $scale = $this->scale($c);
        $period = $this->period($c, true);
        $this->assessment($c, 40, 50, '2026-02-01');
        $this->assessment($c, 60, 100, '2026-02-15');
        $this->assessment($c, 100, 100, '2026-04-15');
        $final = AttendanceSession::query()->create(['organization_id' => $c['organization']->id, 'academic_year_id' => $c['year']->id, 'academic_term_id' => $c['term']->id, 'class_id' => $c['class']->id, 'session_date' => '2026-02-10', 'session_type' => 'class', 'status' => 'finalized', 'created_by' => $c['user']->id]);
        AttendanceEntry::query()->create(['organization_id' => $c['organization']->id, 'attendance_session_id' => $final->id, 'learner_profile_id' => $c['learner']->id, 'status' => 'present', 'recorded_by' => $c['user']->id]);
        $draft = AttendanceSession::query()->create(['organization_id' => $c['organization']->id, 'academic_year_id' => $c['year']->id, 'academic_term_id' => $c['term']->id, 'class_id' => $c['class']->id, 'session_date' => '2026-02-11', 'session_type' => 'class', 'status' => 'draft', 'created_by' => $c['user']->id]);
        AttendanceEntry::query()->create(['organization_id' => $c['organization']->id, 'attendance_session_id' => $draft->id, 'learner_profile_id' => $c['learner']->id, 'status' => 'absent', 'recorded_by' => $c['user']->id]);
        $result = app(ReportCardCalculationService::class)->calculate($c['learner'], $period, $scale);
        $this->assertSame(80.0, $result['overall_average']);
        $this->assertSame(80.0, $result['subjects'][0]['calculated_percentage']);
        $this->assertSame(SubjectResultStatus::Calculated, $result['subjects'][0]['subject_result_status']);
        $this->assertSame('Excellent', $result['subjects'][0]['grading_band_label']);
        $this->assertSame(1, $result['attendance']['attendance_session_count']);
        $this->assertSame(1, $result['attendance']['present_count']);
        $this->assertSame(0, $result['attendance']['absent_count']);
    }

    public function test_calculation_includes_results_from_prior_class_after_mid_period_move(): void
    {
        $c = $this->context('move');
        $scale = $this->scale($c);
        $period = $this->period($c, true);
        $this->assessment($c, null, 80, '2026-02-01');
        $c['learner']->update(['admission_date' => '2026-01-10']);
        $newClass = ClassGroup::query()->create(['organization_id' => $c['organization']->id, 'academic_year_id' => $c['year']->id, 'grade_id' => $c['grade']->id, 'name' => 'Moved '.$c['organization']->code]);
        app(LearnerService::class)->updateAcademicPlacement($c['learner'], $c['user'], ['current_academic_year_id' => $c['year']->id, 'current_grade_id' => $c['grade']->id, 'current_class_id' => $newClass->id]);
        $this->assertDatabaseCount('learner_enrolments', 2);

        $result = app(ReportCardCalculationService::class)->calculate($c['learner']->refresh(), $period, $scale);

        $this->assertSame(80.0, $result['subjects'][0]['calculated_percentage']);
        $this->assertSame(SubjectResultStatus::Calculated, $result['subjects'][0]['subject_result_status']);
        $this->assertSame(80.0, $result['overall_average']);
    }

    public function test_incomplete_weighting_is_insufficient_and_non_marked_is_never_zero(): void
    {
        $c = $this->context('incomplete');
        $scale = $this->scale($c);
        $period = $this->period($c, true);
        $this->assessment($c, 40, 50, '2026-02-01');
        $result = app(ReportCardCalculationService::class)->calculate($c['learner'], $period, $scale);
        $this->assertNull($result['overall_average']);
        $this->assertNull($result['subjects'][0]['calculated_percentage']);
        $this->assertSame(SubjectResultStatus::InsufficientData, $result['subjects'][0]['subject_result_status']);
    }

    public function test_service_generation_lifecycle_versioning_and_published_snapshot_immutability(): void
    {
        $c = $this->context('lifecycle');
        $scale = $this->scale($c);
        $period = $this->period($c, true);
        $template = $this->template($c);
        $this->assessment($c, null, 80, '2026-02-01');
        $service = app(ReportCardService::class);
        $card = $service->generate($c['learner'], $period, $scale, $template, $c['user']);
        $this->assertSame(ReportCardStatus::Generated, $card->status);
        $this->assertSame(1, $card->version_number);
        $service->updateComments($card, $c['user'], ['overall_comment' => '<b>Plain factual comment</b>', 'comment_type' => 'general', 'comment' => 'Progress recorded']);
        $service->transition($card->refresh(), $c['user'], ReportCardStatus::UnderReview);
        $service->transition($card->refresh(), $c['user'], ReportCardStatus::Approved);
        $service->transition($card->refresh(), $c['user'], ReportCardStatus::Published);
        try {
            $service->updateComments($card->refresh(), $c['user'], ['overall_comment' => 'changed']);
            $this->fail('Published report changed.');
        } catch (DomainException) {
        }
        $label = $card->subjects()->firstOrFail()->grading_band_label;
        $scale->bands()->where('label', $label)->update(['label' => 'Changed later']);
        $this->assertSame($label, $card->subjects()->firstOrFail()->grading_band_label);
        $service->transition($card->refresh(), $c['user'], ReportCardStatus::Withdrawn, 'Corrected finalized result required');
        $next = $service->generate($c['learner'], $period, $scale, $template, $c['user'], $card->refresh());
        $this->assertSame(2, $next->version_number);
        $this->assertSame(ReportCardStatus::Withdrawn, $card->refresh()->status);
    }

    public function test_api_web_pdf_csv_and_private_note_boundary(): void
    {
        $c = $this->context('http');
        $scale = $this->scale($c);
        $period = $this->period($c, true);
        $template = $this->template($c);
        $assessment = $this->assessment($c, null, 75, '2026-02-01', 'private note never render');
        $card = app(ReportCardService::class)->generate($c['learner'], $period, $scale, $template, $c['user']);
        $web = $this->actingAs($c['user'])->withSession(['organization_id' => $c['organization']->id]);
        $web->get('/reports')->assertOk()->assertSee('Report cards');
        $web->get('/reports/directory')->assertOk()->assertSee($c['learner']->learner_number);
        $web->get('/reports/'.$card->uuid)->assertOk()->assertDontSee('private note never render');
        $web->get('/learners/'.$c['learner']->uuid.'/report-cards')->assertOk();
        $pdf = $web->get('/reports/'.$card->uuid.'/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringNotContainsString('private note never render', $pdf->getContent());
        $csv = $web->get('/reports-export.csv')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringNotContainsString('private note never render', $csv->streamedContent());
        $headers = ['X-Organization-Id' => $c['organization']->id];
        $this->actingAs($c['user'], 'sanctum')->withHeaders($headers)->getJson('/api/v1/report-cards/'.$card->uuid)->assertOk()->assertJsonMissing(['private_note' => 'private note never render'])->assertJsonMissingPath('data.snapshot_metadata');
        $this->withHeaders($headers)->getJson('/api/v1/learners/'.$c['learner']->uuid.'/report-cards')->assertOk();
    }

    public function test_security_rejects_foreign_resources_unauthenticated_access_get_mutations_and_withdraw_without_reason(): void
    {
        $a = $this->context('secure-a');
        $scaleA = $this->scale($a);
        $periodA = $this->period($a, true);
        $templateA = $this->template($a);
        $this->assessment($a, null, 75, '2026-02-01');
        $cardA = app(ReportCardService::class)->generate($a['learner'], $periodA, $scaleA, $templateA, $a['user']);
        $b = $this->context('secure-b');
        $scaleB = $this->scale($b);
        $periodB = $this->period($b, true);
        $templateB = $this->template($b);
        $this->actingAs($a['user'])->withSession(['organization_id' => $a['organization']->id])->get('/reports/'.$scaleB->uuid)->assertNotFound();
        $this->get('/reports/'.$cardA->uuid.'/publish')->assertStatus(405);
        auth()->logout();
        $this->get('/reports')->assertRedirect('/login');
        $this->actingAs($a['user'])->withSession(['organization_id' => $a['organization']->id]);
        app(ReportCardService::class)->transition($cardA, $a['user'], ReportCardStatus::UnderReview);
        app(ReportCardService::class)->transition($cardA->refresh(), $a['user'], ReportCardStatus::Approved);
        app(ReportCardService::class)->transition($cardA->refresh(), $a['user'], ReportCardStatus::Published);
        $this->post('/reports/'.$cardA->uuid.'/withdraw', [])->assertSessionHasErrors('reason');
    }

    private function scale(array $c): GradingScale
    {
        return app(ReportConfigurationService::class)->saveScale($c['organization'], $c['user'], ['name' => 'Scale '.$c['organization']->code, 'is_active' => true, 'bands' => [['label' => 'Needs support', 'minimum_percentage' => 0, 'maximum_percentage' => 49.99, 'symbol' => 'N'], ['label' => 'Achieved', 'minimum_percentage' => 50, 'maximum_percentage' => 74.99, 'symbol' => 'A'], ['label' => 'Excellent', 'minimum_percentage' => 75, 'maximum_percentage' => 100, 'symbol' => 'E']]]);
    }

    private function period(array $c, bool $open = false): ReportingPeriod
    {
        $p = app(ReportConfigurationService::class)->savePeriod($c['organization'], $c['user'], ['academic_year_id' => $c['year']->id, 'academic_term_id' => $c['term']->id, 'name' => 'Term report '.$c['organization']->code, 'code' => 'T1', 'start_date' => '2026-01-01', 'end_date' => '2026-03-31', 'result_cutoff_date' => '2026-03-31']);

        return $open ? app(ReportConfigurationService::class)->transitionPeriod($p, $c['user'], ReportingPeriodStatus::Open) : $p;
    }

    private function template(array $c): ReportCardTemplate
    {
        return app(ReportConfigurationService::class)->saveTemplate($c['organization'], $c['user'], ['name' => 'Standard '.$c['organization']->code, 'is_active' => true, 'show_attendance' => true, 'show_assessment_breakdown' => true, 'show_subject_comments' => true, 'show_overall_comment' => true, 'show_grading_legend' => true, 'show_organization_logo' => true, 'page_size' => 'A4']);
    }

    private function assessment(array $c, ?float $weight, float $percentage, string $date, ?string $privateNote = null): Assessment
    {
        $a = app(AssessmentService::class)->create($c['organization'], $c['user'], ['academic_year_id' => $c['year']->id, 'academic_term_id' => $c['term']->id, 'grade_id' => $c['grade']->id, 'class_id' => $c['class']->id, 'subject_id' => $c['subject']->id, 'assessment_category_id' => $c['category']->id, 'title' => 'Assessment '.$date.' '.($weight ?? 'none'), 'assessment_date' => $date, 'maximum_mark' => 100, 'weighting' => $weight]);
        $result = $a->results->first();
        app(AssessmentResultService::class)->record($a, $c['user'], [['result_uuid' => $result->uuid, 'result_status' => 'marked', 'score' => $percentage, 'private_note' => $privateNote]]);

        return app(AssessmentService::class)->finalize($a->refresh(), $c['user']);
    }

    private function context(string $code): array
    {
        $this->seed([AssessmentsPermissionSeeder::class, ReportsPermissionSeeder::class]);
        $o = Organization::query()->create(['name' => $code, 'code' => $code, 'type' => 'school']);
        foreach (['academics', 'learners', 'staff', 'attendance', 'assessments', 'reports'] as $module) {
            OrganizationModule::query()->create(['organization_id' => $o->id, 'module_name' => $module, 'enabled' => true]);
        } $u = User::factory()->create();
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
