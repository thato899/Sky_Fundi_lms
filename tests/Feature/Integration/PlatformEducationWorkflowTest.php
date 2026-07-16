<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use Core\AuditLogs\Infrastructure\Models\AuditLog;
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
use Modules\Attendance\Application\AttendanceRecordingService;
use Modules\Attendance\Application\AttendanceSessionService;
use Modules\Attendance\Database\Seeders\AttendancePermissionSeeder;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Infrastructure\Models\OrganizationModule;
use Modules\Reports\Application\ReportCardService;
use Modules\Reports\Application\ReportConfigurationService;
use Modules\Reports\Database\Seeders\ReportsPermissionSeeder;
use Modules\Reports\Domain\Enums\ReportingPeriodStatus;
use Modules\Scheduling\Application\LessonService;
use Modules\Scheduling\Database\Seeders\SchedulingPermissionSeeder;
use Modules\Staff\Infrastructure\Models\StaffProfile;
use Tests\TestCase;

final class PlatformEducationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_academics_to_reports_workflow_preserves_tenant_ownership_and_finalized_facts(): void
    {
        $context = $this->context('workflow');
        $lesson = app(LessonService::class)->create($context['organization'], $context['user'], [
            'academic_year_id' => $context['year']->id,
            'academic_term_id' => $context['term']->id,
            'grade_id' => $context['grade']->id,
            'class_id' => $context['class']->id,
            'subject_id' => $context['subject']->id,
            'lesson_date' => '2026-02-02',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'delivery_mode' => 'in_person',
            'staff' => [['staff_profile_id' => $context['staff']->id, 'assignment_type' => 'teacher', 'is_primary' => true]],
        ]);

        $attendance = app(LessonService::class)->createAttendance($lesson, $context['organization'], $context['user'])->load('entries');
        $entry = $attendance->entries->sole();
        app(AttendanceRecordingService::class)->record($attendance, $context['user'], [['entry_uuid' => $entry->uuid, 'status' => 'present']]);
        app(AttendanceSessionService::class)->finalize($attendance->refresh(), $context['user']);

        $category = app(AssessmentCategoryService::class)->create($context['organization'], $context['user'], ['name' => 'Class test', 'code' => 'CT']);
        $assessment = app(AssessmentService::class)->create($context['organization'], $context['user'], [
            'academic_year_id' => $context['year']->id,
            'academic_term_id' => $context['term']->id,
            'grade_id' => $context['grade']->id,
            'class_id' => $context['class']->id,
            'subject_id' => $context['subject']->id,
            'assessment_category_id' => $category->id,
            'staff_profile_id' => $context['staff']->id,
            'title' => 'Term test',
            'assessment_date' => '2026-02-03',
            'maximum_mark' => 50,
        ]);
        $result = $assessment->results->sole();
        app(AssessmentResultService::class)->record($assessment, $context['user'], [['result_uuid' => $result->uuid, 'result_status' => 'marked', 'score' => 40]]);
        app(AssessmentService::class)->finalize($assessment->refresh(), $context['user']);

        $configuration = app(ReportConfigurationService::class);
        $scale = $configuration->saveScale($context['organization'], $context['user'], [
            'name' => 'Standard',
            'is_active' => true,
            'bands' => [
                ['label' => 'Not achieved', 'minimum_percentage' => 0, 'maximum_percentage' => 49.99],
                ['label' => 'Achieved', 'minimum_percentage' => 50, 'maximum_percentage' => 100],
            ],
        ]);
        $period = $configuration->savePeriod($context['organization'], $context['user'], [
            'academic_year_id' => $context['year']->id,
            'academic_term_id' => $context['term']->id,
            'name' => 'Term 1',
            'code' => 'T1',
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-31',
        ]);
        $period = $configuration->transitionPeriod($period, $context['user'], ReportingPeriodStatus::Open);
        $template = $configuration->saveTemplate($context['organization'], $context['user'], ['name' => 'Standard', 'is_active' => true, 'show_attendance' => true, 'page_size' => 'A4']);
        $card = app(ReportCardService::class)->generate($context['learner'], $period, $scale, $template, $context['user']);

        $this->assertSame($context['organization']->id, $lesson->organization_id);
        $this->assertSame($lesson->id, $attendance->scheduled_lesson_id);
        $this->assertSame('80.00', $card->subjects->sole()->calculated_percentage);
        $this->assertSame(1, $card->attendance_session_count);
        $this->assertSame(1, $card->present_count);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reports.report_generated', 'target_id' => $card->id]);
        $audit = AuditLog::query()->where('target_id', $card->id)->firstOrFail();
        $this->assertSame($context['organization']->id, $audit->after['organization_id']);
    }

    public function test_foreign_relationships_fail_without_creating_cross_tenant_workflow_records(): void
    {
        $local = $this->context('local');
        $foreign = $this->context('foreign');

        try {
            app(LessonService::class)->create($local['organization'], $local['user'], [
                'academic_year_id' => $local['year']->id,
                'academic_term_id' => $local['term']->id,
                'grade_id' => $local['grade']->id,
                'class_id' => $local['class']->id,
                'subject_id' => $local['subject']->id,
                'lesson_date' => '2026-02-02',
                'start_time' => '09:00',
                'end_time' => '10:00',
                'delivery_mode' => 'in_person',
                'staff' => [['staff_profile_id' => $foreign['staff']->id]],
            ]);
            $this->fail('A foreign staff relationship was accepted.');
        } catch (DomainException $exception) {
            $this->assertSame('The staff member must belong to the active organization.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('scheduled_lesson_staff', ['organization_id' => $local['organization']->id, 'staff_profile_id' => $foreign['staff']->id]);
        $this->actingAs($local['user'], 'sanctum')
            ->withHeader('X-Organization-Id', $local['organization']->id)
            ->getJson('/api/v1/learners/'.$foreign['learner']->uuid.'/report-cards')
            ->assertNotFound();
    }

    private function context(string $code): array
    {
        $this->seed([AttendancePermissionSeeder::class, AssessmentsPermissionSeeder::class, ReportsPermissionSeeder::class, SchedulingPermissionSeeder::class]);
        $organization = Organization::query()->create(['name' => ucfirst($code), 'code' => $code, 'type' => 'school', 'timezone' => 'Africa/Johannesburg']);
        foreach (['academics', 'staff', 'learners', 'attendance', 'assessments', 'reports', 'scheduling'] as $module) {
            OrganizationModule::query()->create(['organization_id' => $organization->id, 'module_name' => $module, 'enabled' => true]);
        }
        $role = Role::query()->where('name', 'Organization Administrator')->firstOrFail();
        $user = User::factory()->create();
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
        $year = AcademicYear::query()->create(['organization_id' => $organization->id, 'name' => '2026 '.$code, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $term = AcademicTerm::query()->create(['organization_id' => $organization->id, 'academic_year_id' => $year->id, 'term_number' => 1, 'name' => 'Term 1', 'start_date' => '2026-01-01', 'end_date' => '2026-03-31']);
        $grade = Grade::query()->create(['organization_id' => $organization->id, 'academic_year_id' => $year->id, 'name' => 'Grade '.$code, 'order' => 1]);
        $class = ClassGroup::query()->create(['organization_id' => $organization->id, 'academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => 'Class '.$code]);
        $subject = Subject::query()->create(['organization_id' => $organization->id, 'name' => 'Mathematics '.$code, 'code' => 'M'.$code]);
        $learner = LearnerProfile::factory()->create(['organization_id' => $organization->id, 'current_academic_year_id' => $year->id, 'current_grade_id' => $grade->id, 'current_class_id' => $class->id, 'learner_status' => 'active']);
        $staffUser = User::factory()->create();
        $staffMembership = Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $staffUser->id, 'role_id' => $role->id, 'status' => 'active']);
        $staff = StaffProfile::query()->create(['organization_id' => $organization->id, 'organization_membership_id' => $staffMembership->id, 'user_id' => $staffUser->id, 'employee_number' => 'E-'.$code, 'staff_type' => 'teacher', 'employment_status' => 'active', 'first_name' => 'Teacher', 'last_name' => $code]);
        $this->actingAs($user)->withSession(['organization_id' => $organization->id]);

        return compact('organization', 'user', 'year', 'term', 'grade', 'class', 'subject', 'learner', 'staff');
    }
}
