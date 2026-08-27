<?php

declare(strict_types=1);

namespace Modules\Leaderboards\Tests\Feature;

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
use Modules\Leaderboards\Application\LeaderboardService;
use Modules\Leaderboards\Database\Seeders\LeaderboardsPermissionSeeder;
use Modules\Leaderboards\Infrastructure\Models\SportsRecord;
use Modules\Learners\Infrastructure\Models\GuardianProfile;
use Modules\Learners\Infrastructure\Models\LearnerGuardianRelationship;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Infrastructure\Models\OrganizationModule;
use Modules\Reports\Domain\Enums\ReportCardStatus;
use Modules\Reports\Domain\Enums\ReportingPeriodStatus;
use Modules\Reports\Infrastructure\Models\GradingScale;
use Modules\Reports\Infrastructure\Models\GradingScaleBand;
use Modules\Reports\Infrastructure\Models\ReportCard;
use Modules\Reports\Infrastructure\Models\ReportCardSubjectResult;
use Modules\Reports\Infrastructure\Models\ReportCardTemplate;
use Modules\Reports\Infrastructure\Models\ReportingPeriod;
use Tests\TestCase;

final class LeaderboardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_academic_leaderboard_ranks_by_overall_average_with_standard_competition_ties(): void
    {
        $c = $this->context('academic-rank');
        $a = $this->approvedCard($c, $this->learner($c, 'A'), 90.0);
        $b = $this->approvedCard($c, $this->learner($c, 'B'), 80.0);
        $tie1 = $this->approvedCard($c, $this->learner($c, 'Tie1'), 70.0);
        $tie2 = $this->approvedCard($c, $this->learner($c, 'Tie2'), 70.0);

        $board = app(LeaderboardService::class)->generateAcademic($c['organization'], $c['actor'], ['reporting_period_id' => $c['period']->id]);

        $ranks = $board->entries()->with('learner')->orderBy('rank')->get()->mapWithKeys(fn ($e) => [$e->learner->first_name => $e->rank]);
        $this->assertSame(1, $ranks['A']);
        $this->assertSame(2, $ranks['B']);
        // Standard competition ranking: a tie at 70% shares rank 3, and
        // there is no rank 4 — the next distinct value would be rank 5.
        $this->assertSame(3, $ranks['Tie1']);
        $this->assertSame(3, $ranks['Tie2']);
    }

    public function test_academic_leaderboard_by_subject_uses_the_subject_percentage_not_overall_average(): void
    {
        $c = $this->context('academic-subject');
        $learner = $this->learner($c, 'Solo');
        $card = $this->approvedCard($c, $learner, 60.0);
        ReportCardSubjectResult::query()->create(['organization_id' => $c['organization']->id, 'report_card_id' => $card->id, 'subject_id' => $c['subject']->id, 'subject_name_snapshot' => $c['subject']->name, 'calculated_percentage' => 95.0, 'subject_result_status' => 'calculated', 'display_order' => 0]);

        $board = app(LeaderboardService::class)->generateAcademic($c['organization'], $c['actor'], ['reporting_period_id' => $c['period']->id, 'subject_id' => $c['subject']->id]);

        $this->assertSame(95.0, (float) $board->entries()->first()->value);
    }

    public function test_only_approved_or_published_report_cards_are_eligible(): void
    {
        $c = $this->context('academic-draft-excluded');
        $learner = $this->learner($c, 'Draft');
        ReportCard::query()->create(['organization_id' => $c['organization']->id, 'learner_profile_id' => $learner->id, 'reporting_period_id' => $c['period']->id, 'academic_year_id' => $c['year']->id, 'grade_id' => $c['grade']->id, 'class_id' => $c['class']->id, 'grading_scale_id' => $c['scale']->id, 'report_card_template_id' => $c['template']->id, 'version_number' => 1, 'status' => ReportCardStatus::Generated, 'overall_average' => 99.0, 'generated_at' => now(), 'generated_by' => $c['actor']->id]);

        $this->expectException(DomainException::class);
        app(LeaderboardService::class)->generateAcademic($c['organization'], $c['actor'], ['reporting_period_id' => $c['period']->id]);
    }

    public function test_sports_leaderboard_sums_points_within_the_date_range_only(): void
    {
        $c = $this->context('sports-sum');
        $learner = $this->learner($c, 'Athlete');
        SportsRecord::query()->create(['organization_id' => $c['organization']->id, 'learner_profile_id' => $learner->id, 'activity' => 'Sprint', 'points' => 10, 'event_date' => '2026-02-01', 'recorded_by' => $c['actor']->id]);
        SportsRecord::query()->create(['organization_id' => $c['organization']->id, 'learner_profile_id' => $learner->id, 'activity' => 'Long jump', 'points' => 5, 'event_date' => '2026-02-10', 'recorded_by' => $c['actor']->id]);
        SportsRecord::query()->create(['organization_id' => $c['organization']->id, 'learner_profile_id' => $learner->id, 'activity' => 'Out of range', 'points' => 100, 'event_date' => '2026-05-01', 'recorded_by' => $c['actor']->id]);

        $board = app(LeaderboardService::class)->generateSports($c['organization'], $c['actor'], ['period_start_date' => '2026-02-01', 'period_end_date' => '2026-02-28']);

        $this->assertSame(15.0, (float) $board->entries()->first()->value);
    }

    public function test_generate_without_eligible_data_is_rejected_for_both_types(): void
    {
        $c = $this->context('empty');
        $service = app(LeaderboardService::class);

        try {
            $service->generateAcademic($c['organization'], $c['actor'], ['reporting_period_id' => $c['period']->id]);
            $this->fail('Empty academic generation was accepted.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('No eligible', $exception->getMessage());
        }
        try {
            $service->generateSports($c['organization'], $c['actor'], ['period_start_date' => '2026-01-01', 'period_end_date' => '2026-01-31']);
            $this->fail('Empty sports generation was accepted.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('No sports records', $exception->getMessage());
        }
    }

    public function test_a_learner_sees_only_their_own_entry_until_published_then_the_full_list(): void
    {
        $c = $this->context('visibility-learner');
        $topLearner = $this->learner($c, 'Top');
        $this->approvedCard($c, $topLearner, 95.0);
        $ownLearnerUser = User::factory()->create();
        $learnerRole = Role::query()->firstOrCreate(['name' => 'Learner'], ['is_system' => false]);
        Membership::query()->create(['organization_id' => $c['organization']->id, 'user_id' => $ownLearnerUser->id, 'role_id' => $learnerRole->id, 'status' => 'active', 'is_default' => true]);
        $ownLearner = $this->learner($c, 'Own', $ownLearnerUser);
        $this->approvedCard($c, $ownLearner, 55.0);
        $board = app(LeaderboardService::class)->generateAcademic($c['organization'], $c['actor'], ['reporting_period_id' => $c['period']->id]);

        $headers = ['X-Organization-Id' => $c['organization']->id];
        $before = $this->actingAs($ownLearnerUser, 'sanctum')->withHeaders($headers)->getJson("/api/v1/leaderboards/{$board->uuid}")->assertOk();
        $before->assertJsonPath('data.fully_visible', false);
        $before->assertJsonCount(1, 'data.entries');
        $before->assertJsonPath('data.entries.0.learner_name', null); // never a name while private
        $before->assertJsonPath('data.entries.0.is_you', true);

        app(LeaderboardService::class)->publish($board, $c['actor']);

        $after = $this->actingAs($ownLearnerUser, 'sanctum')->withHeaders($headers)->getJson("/api/v1/leaderboards/{$board->uuid}")->assertOk();
        $after->assertJsonPath('data.fully_visible', true);
        $after->assertJsonCount(2, 'data.entries');
        $this->assertNotNull($after->json('data.entries.0.learner_name'));
    }

    public function test_a_guardian_sees_only_their_linked_learners_entries_not_other_learners(): void
    {
        $c = $this->context('visibility-guardian');
        $guardianUser = User::factory()->create();
        $ownLearner = $this->learner($c, 'MyChild');
        $this->linkGuardian($c, $guardianUser, $ownLearner);
        $otherLearner = $this->learner($c, 'OtherChild');
        $this->approvedCard($c, $ownLearner, 60.0);
        $this->approvedCard($c, $otherLearner, 99.0);
        $board = app(LeaderboardService::class)->generateAcademic($c['organization'], $c['actor'], ['reporting_period_id' => $c['period']->id]);

        $response = $this->actingAs($guardianUser, 'sanctum')->withHeaders(['X-Organization-Id' => $c['organization']->id])
            ->getJson("/api/v1/leaderboards/{$board->uuid}")->assertOk();
        $response->assertJsonCount(1, 'data.entries');
    }

    public function test_view_organization_permission_sees_the_full_list_even_while_private(): void
    {
        $c = $this->context('visibility-teacher');
        $this->approvedCard($c, $this->learner($c, 'One'), 60.0);
        $this->approvedCard($c, $this->learner($c, 'Two'), 70.0);
        $board = app(LeaderboardService::class)->generateAcademic($c['organization'], $c['actor'], ['reporting_period_id' => $c['period']->id]);

        $teacherRole = Role::query()->where('name', 'Teacher')->firstOrFail();
        $teacherUser = User::factory()->create();
        Membership::query()->create(['organization_id' => $c['organization']->id, 'user_id' => $teacherUser->id, 'role_id' => $teacherRole->id, 'status' => 'active', 'is_default' => true]);

        $response = $this->actingAs($teacherUser, 'sanctum')->withHeaders(['X-Organization-Id' => $c['organization']->id])
            ->getJson("/api/v1/leaderboards/{$board->uuid}")->assertOk();
        $response->assertJsonPath('data.fully_visible', true)->assertJsonCount(2, 'data.entries');
    }

    public function test_generation_and_publishing_require_the_manage_permission(): void
    {
        $c = $this->context('permission-gate');
        $this->approvedCard($c, $this->learner($c, 'Someone'), 60.0);
        $teacherRole = Role::query()->where('name', 'Teacher')->firstOrFail();
        $teacherUser = User::factory()->create();
        Membership::query()->create(['organization_id' => $c['organization']->id, 'user_id' => $teacherUser->id, 'role_id' => $teacherRole->id, 'status' => 'active', 'is_default' => true]);
        $headers = ['X-Organization-Id' => $c['organization']->id];

        // Teacher holds view_organization but not manage — cannot generate.
        $this->actingAs($teacherUser, 'sanctum')->withHeaders($headers)
            ->postJson('/api/v1/leaderboards/academic', ['reporting_period_id' => $c['period']->id])
            ->assertForbidden();

        $this->actingAs($c['actor'], 'sanctum')->withHeaders($headers)
            ->postJson('/api/v1/leaderboards/academic', ['reporting_period_id' => $c['period']->id])
            ->assertCreated();
    }

    private function linkGuardian(array $c, User $guardianUser, LearnerProfile $learner): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'Guardian'], ['is_system' => false]);
        $membership = Membership::query()->create(['organization_id' => $c['organization']->id, 'user_id' => $guardianUser->id, 'role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
        $guardian = GuardianProfile::query()->create(['organization_id' => $c['organization']->id, 'organization_membership_id' => $membership->id, 'user_id' => $guardianUser->id, 'first_name' => 'G', 'last_name' => 'Parent', 'status' => 'active']);
        LearnerGuardianRelationship::query()->create(['organization_id' => $c['organization']->id, 'learner_profile_id' => $learner->id, 'guardian_profile_id' => $guardian->id, 'relationship_type' => 'parent', 'status' => 'active']);
    }

    private function learner(array $c, string $name, ?User $user = null): LearnerProfile
    {
        return LearnerProfile::factory()->create([
            'organization_id' => $c['organization']->id, 'first_name' => $name, 'last_name' => 'Learner',
            'user_id' => $user?->id, 'current_academic_year_id' => $c['year']->id, 'current_grade_id' => $c['grade']->id, 'current_class_id' => $c['class']->id,
        ]);
    }

    private function approvedCard(array $c, LearnerProfile $learner, float $average): ReportCard
    {
        return ReportCard::query()->create([
            'organization_id' => $c['organization']->id, 'learner_profile_id' => $learner->id, 'reporting_period_id' => $c['period']->id,
            'academic_year_id' => $c['year']->id, 'grade_id' => $c['grade']->id, 'class_id' => $c['class']->id,
            'grading_scale_id' => $c['scale']->id, 'report_card_template_id' => $c['template']->id,
            'version_number' => 1, 'status' => ReportCardStatus::Approved, 'overall_average' => $average,
            'generated_at' => now(), 'generated_by' => $c['actor']->id, 'approved_at' => now(), 'approved_by' => $c['actor']->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function context(string $code): array
    {
        $this->seed(LeaderboardsPermissionSeeder::class);
        $organization = Organization::query()->create(['name' => $code, 'code' => $code, 'type' => 'school']);
        foreach (['leaderboards', 'reports', 'academics', 'learners'] as $module) {
            OrganizationModule::query()->create(['organization_id' => $organization->id, 'module_name' => $module, 'enabled' => true]);
        }
        $actor = User::factory()->create();
        $adminRole = Role::query()->firstOrCreate(['name' => 'Organization Administrator'], ['is_system' => false]);
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $actor->id, 'role_id' => $adminRole->id, 'status' => 'active', 'is_default' => true]);
        $this->actingAs($actor, 'sanctum');

        $year = AcademicYear::query()->create(['organization_id' => $organization->id, 'name' => '2026 '.$code, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $term = AcademicTerm::query()->create(['organization_id' => $organization->id, 'academic_year_id' => $year->id, 'term_number' => 1, 'name' => 'Term 1', 'start_date' => '2026-01-01', 'end_date' => '2026-03-31']);
        $grade = Grade::query()->create(['organization_id' => $organization->id, 'academic_year_id' => $year->id, 'name' => 'Grade '.$code, 'order' => 1]);
        $class = ClassGroup::query()->create(['organization_id' => $organization->id, 'academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => 'Class '.$code]);
        $subject = Subject::query()->create(['organization_id' => $organization->id, 'name' => 'Subject '.$code, 'code' => 'S-'.$code]);

        $scale = GradingScale::query()->create(['organization_id' => $organization->id, 'name' => 'Scale '.$code, 'pass_threshold' => 50, 'is_active' => true, 'is_default' => true, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
        GradingScaleBand::query()->create(['organization_id' => $organization->id, 'grading_scale_id' => $scale->id, 'label' => 'Pass', 'minimum_percentage' => 0, 'maximum_percentage' => 100, 'is_passing' => true, 'display_order' => 0]);
        $template = ReportCardTemplate::query()->create(['organization_id' => $organization->id, 'name' => 'Template '.$code, 'is_active' => true, 'is_default' => true, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
        $period = ReportingPeriod::query()->create(['organization_id' => $organization->id, 'academic_year_id' => $year->id, 'academic_term_id' => $term->id, 'name' => 'Period '.$code, 'code' => 'P-'.$code, 'start_date' => '2026-01-01', 'end_date' => '2026-03-31', 'status' => ReportingPeriodStatus::Open, 'created_by' => $actor->id, 'updated_by' => $actor->id]);

        return compact('organization', 'actor', 'year', 'term', 'grade', 'class', 'subject', 'scale', 'template', 'period');
    }
}
