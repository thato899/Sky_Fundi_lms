<?php

declare(strict_types=1);

namespace Modules\Learners\Tests\Feature;

use Core\Identity\Infrastructure\Models\Membership;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Learners\Application\LearnerEnrolmentService;
use Modules\Learners\Application\LearnerService;
use Modules\Learners\Infrastructure\Models\LearnerEnrolment;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Tests\TestCase;

final class LearnerEnrolmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_creates_enrolment_schema_and_single_open_row_constraint(): void
    {
        $this->assertTrue(Schema::hasTable('learner_enrolments'));
        $this->assertTrue(Schema::hasColumns('learner_enrolments', [
            'organization_id', 'learner_profile_id', 'academic_year_id', 'grade_id',
            'class_id', 'curriculum_id', 'started_on', 'ended_on', 'actor_id',
        ]));

        [$organization] = $this->context('schema');
        $learner = LearnerProfile::factory()->create(['organization_id' => $organization->id]);
        LearnerEnrolment::query()->create(['organization_id' => $organization->id, 'learner_profile_id' => $learner->id, 'started_on' => '2026-01-01']);

        $this->expectException(QueryException::class);
        LearnerEnrolment::query()->create(['organization_id' => $organization->id, 'learner_profile_id' => $learner->id, 'started_on' => '2026-02-01']);
    }

    public function test_create_with_placement_opens_enrolment_and_placement_change_closes_and_reopens(): void
    {
        [$organization, $actor] = $this->context('lifecycle');
        [$year, $grade, $classA, $classB] = $this->academics($organization);
        $service = app(LearnerService::class);

        $learner = $service->create($organization, $actor, [
            'first_name' => 'Enrol', 'last_name' => 'History',
            'current_academic_year_id' => $year->id, 'current_grade_id' => $grade->id, 'current_class_id' => $classA->id,
        ], false);
        $open = LearnerEnrolment::query()->where('learner_profile_id', $learner->id)->whereNull('ended_on')->sole();
        $this->assertSame($classA->id, $open->class_id);
        $this->assertSame($year->id, $open->academic_year_id);
        $this->assertSame($actor->id, $open->actor_id);
        $this->assertSame(now()->toDateString(), $open->started_on->toDateString());

        $service->updateAcademicPlacement($learner, $actor, ['current_academic_year_id' => $year->id, 'current_grade_id' => $grade->id, 'current_class_id' => $classA->id]);
        $this->assertDatabaseCount('learner_enrolments', 1);

        $service->updateAcademicPlacement($learner->refresh(), $actor, ['current_academic_year_id' => $year->id, 'current_grade_id' => $grade->id, 'current_class_id' => $classB->id]);
        $this->assertDatabaseCount('learner_enrolments', 2);
        $this->assertSame(now()->toDateString(), $open->refresh()->ended_on->toDateString());
        $this->assertSame($classB->id, LearnerEnrolment::query()->where('learner_profile_id', $learner->id)->whereNull('ended_on')->sole()->class_id);
    }

    public function test_clearing_placement_closes_the_open_enrolment_without_opening_a_new_row(): void
    {
        [$organization, $actor] = $this->context('clear');
        [$year, $grade, $classA] = $this->academics($organization);
        $service = app(LearnerService::class);
        $learner = $service->create($organization, $actor, [
            'first_name' => 'Clear', 'last_name' => 'Placement',
            'current_academic_year_id' => $year->id, 'current_grade_id' => $grade->id, 'current_class_id' => $classA->id,
        ], false);

        $service->updateAcademicPlacement($learner, $actor, ['current_academic_year_id' => null, 'current_grade_id' => null, 'current_class_id' => null, 'curriculum_id' => null]);

        $this->assertDatabaseCount('learner_enrolments', 1);
        $this->assertSame(0, LearnerEnrolment::query()->whereNull('ended_on')->count());
    }

    public function test_placement_change_records_superseded_placement_for_learners_created_before_tracking(): void
    {
        [$organization, $actor] = $this->context('selfheal');
        [$year, $grade, $classA, $classB] = $this->academics($organization);
        $learner = LearnerProfile::factory()->create([
            'organization_id' => $organization->id, 'admission_date' => '2026-01-10',
            'current_academic_year_id' => $year->id, 'current_grade_id' => $grade->id, 'current_class_id' => $classA->id,
        ]);
        $this->assertDatabaseCount('learner_enrolments', 0);

        app(LearnerService::class)->updateAcademicPlacement($learner, $actor, ['current_academic_year_id' => $year->id, 'current_grade_id' => $grade->id, 'current_class_id' => $classB->id]);

        $closed = LearnerEnrolment::query()->whereNotNull('ended_on')->sole();
        $this->assertSame($classA->id, $closed->class_id);
        $this->assertSame('2026-01-10', $closed->started_on->toDateString());
        $this->assertSame($classB->id, LearnerEnrolment::query()->whereNull('ended_on')->sole()->class_id);
    }

    public function test_class_ids_during_resolves_window_overlap_and_stays_learner_scoped(): void
    {
        [$organization] = $this->context('window');
        [$year, $grade, $classA, $classB] = $this->academics($organization);
        $learner = LearnerProfile::factory()->create(['organization_id' => $organization->id]);
        $other = LearnerProfile::factory()->create(['organization_id' => $organization->id]);
        $base = ['organization_id' => $organization->id, 'academic_year_id' => $year->id, 'grade_id' => $grade->id];
        LearnerEnrolment::query()->create([...$base, 'learner_profile_id' => $learner->id, 'class_id' => $classA->id, 'started_on' => '2026-01-01', 'ended_on' => '2026-02-14']);
        LearnerEnrolment::query()->create([...$base, 'learner_profile_id' => $learner->id, 'class_id' => $classB->id, 'started_on' => '2026-02-15']);
        LearnerEnrolment::query()->create([...$base, 'learner_profile_id' => $other->id, 'class_id' => $classA->id, 'started_on' => '2026-01-01']);

        $service = app(LearnerEnrolmentService::class);
        $this->assertSame([$classA->id, $classB->id], $service->classIdsDuring($learner, '2026-01-01', '2026-03-31'));
        $this->assertSame([$classA->id], $service->classIdsDuring($learner, '2026-01-05', '2026-02-01'));
        $this->assertSame([$classB->id], $service->classIdsDuring($learner, '2026-03-01', '2026-03-31'));
    }

    /** @return array{AcademicYear, Grade, ClassGroup, ClassGroup} */
    private function academics(Organization $organization): array
    {
        $year = AcademicYear::query()->create(['organization_id' => $organization->id, 'name' => '2026 '.$organization->code, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'current']);
        $grade = Grade::query()->create(['organization_id' => $organization->id, 'name' => 'Grade '.$organization->code, 'order' => 1, 'academic_year_id' => $year->id]);
        $classA = ClassGroup::query()->create(['organization_id' => $organization->id, 'name' => 'A '.$organization->code, 'academic_year_id' => $year->id, 'grade_id' => $grade->id]);
        $classB = ClassGroup::query()->create(['organization_id' => $organization->id, 'name' => 'B '.$organization->code, 'academic_year_id' => $year->id, 'grade_id' => $grade->id]);

        return [$year, $grade, $classA, $classB];
    }

    /** @return array{Organization, User} */
    private function context(string $code): array
    {
        $organization = Organization::query()->create(['name' => $code, 'code' => $code, 'type' => 'school']);
        $actor = User::factory()->create();
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $actor->id, 'status' => 'active']);
        $this->actingAs($actor);

        return [$organization, $actor];
    }
}
