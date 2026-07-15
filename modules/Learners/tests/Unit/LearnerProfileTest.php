<?php

declare(strict_types=1);

namespace Modules\Learners\Tests\Unit;

use Core\Identity\Infrastructure\Models\Membership;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Curriculum;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Learners\Domain\Enums\LearnerStatus;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Tests\TestCase;

final class LearnerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_only_learner_can_be_created_with_casts_and_organization(): void
    {
        $organization = $this->organization();
        $profile = LearnerProfile::factory()->create([
            'organization_id' => $organization->id,
            'date_of_birth' => '2012-04-03',
            'admission_date' => '2026-01-15',
            'expected_completion_date' => '2030-12-01',
            'metadata' => ['support' => 'none'],
        ]);

        $this->assertNotNull($profile->id);
        $this->assertNotNull($profile->uuid);
        $this->assertSame(LearnerStatus::Pending, $profile->learner_status);
        $this->assertSame(['support' => 'none'], $profile->metadata);
        $this->assertInstanceOf(Carbon::class, $profile->date_of_birth);
        $this->assertInstanceOf(Carbon::class, $profile->admission_date);
        $this->assertInstanceOf(Carbon::class, $profile->expected_completion_date);
        $this->assertTrue($profile->organization->is($organization));
        $this->assertNull($profile->user);
        $this->assertNull($profile->organizationMembership);
    }

    public function test_user_and_membership_relationships_work(): void
    {
        $organization = $this->organization();
        $user = User::factory()->create();
        $membership = Membership::create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
        ]);
        $profile = LearnerProfile::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'organization_membership_id' => $membership->id,
        ]);

        $this->assertTrue($profile->user->is($user));
        $this->assertTrue($profile->organizationMembership->is($membership));
    }

    public function test_academic_relationships_work(): void
    {
        $organization = $this->organization();
        $curriculum = Curriculum::create(['organization_id' => $organization->id, 'name' => 'CAPS', 'code' => 'CAPS']);
        $year = AcademicYear::create([
            'organization_id' => $organization->id,
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        $grade = Grade::create([
            'organization_id' => $organization->id,
            'name' => 'Grade 8',
            'order' => 8,
            'curriculum_id' => $curriculum->id,
            'academic_year_id' => $year->id,
        ]);
        $class = ClassGroup::create([
            'organization_id' => $organization->id,
            'name' => '8A',
            'academic_year_id' => $year->id,
            'grade_id' => $grade->id,
        ]);
        $profile = LearnerProfile::factory()->create([
            'organization_id' => $organization->id,
            'current_academic_year_id' => $year->id,
            'current_grade_id' => $grade->id,
            'current_class_id' => $class->id,
            'curriculum_id' => $curriculum->id,
        ]);

        $this->assertTrue($profile->currentAcademicYear->is($year));
        $this->assertTrue($profile->currentGrade->is($grade));
        $this->assertTrue($profile->currentClass->is($class));
        $this->assertTrue($profile->curriculum->is($curriculum));
    }

    public function test_soft_deletion_works(): void
    {
        $profile = LearnerProfile::factory()->create(['organization_id' => $this->organization()->id]);
        $profile->delete();

        $this->assertSoftDeleted('learner_profiles', ['id' => $profile->id]);
        $this->assertNull(LearnerProfile::find($profile->id));
    }

    public function test_factory_states_work(): void
    {
        $organization = $this->organization();
        $active = LearnerProfile::factory()->active()->create(['organization_id' => $organization->id]);
        $suspended = LearnerProfile::factory()->suspended()->create(['organization_id' => $organization->id]);
        $archived = LearnerProfile::factory()->archived()->create(['organization_id' => $organization->id]);

        $this->assertSame(LearnerStatus::Active, $active->learner_status);
        $this->assertSame(LearnerStatus::Suspended, $suspended->learner_status);
        $this->assertSame(LearnerStatus::Archived, $archived->learner_status);
        $this->assertInstanceOf(Carbon::class, $archived->archived_at);
    }

    private function organization(): Organization
    {
        return Organization::create([
            'name' => 'Sky Fundi School',
            'code' => 'sky-fundi-'.fake()->unique()->numerify('####'),
            'type' => 'school',
        ]);
    }
}
