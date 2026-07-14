<?php

declare(strict_types=1);

namespace Modules\Learners\Tests\Feature;

use Core\Identity\Infrastructure\Models\Membership;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Learners\Application\LearnerService;
use Modules\Learners\Domain\Enums\LearnerStatus;
use Modules\Organizations\Infrastructure\Models\Organization;
use Tests\TestCase;

final class LearnerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_creation_update_placement_status_archive_and_restore_use_existing_services(): void
    {
        [$organization, $actor] = $this->context('service');
        $service = app(LearnerService::class);
        $learner = $service->create($organization, $actor, ['first_name' => 'Ava', 'last_name' => 'Mokoena'], false);

        $this->assertSame('LRN-000001', $learner->learner_number);
        $this->assertNull($learner->user_id);
        $this->assertNull($learner->organization_membership_id);
        $this->assertFalse($learner->portal_access_enabled);

        $updated = $service->update($learner, $actor, ['preferred_name' => 'Avie']);
        $this->assertSame('Avie', $updated->preferred_name);

        $year = AcademicYear::query()->create(['name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'current']);
        $grade = Grade::query()->create(['name' => 'Grade 8', 'order' => 8, 'academic_year_id' => $year->id]);
        $class = ClassGroup::query()->create(['name' => '8A', 'academic_year_id' => $year->id, 'grade_id' => $grade->id]);
        $placed = $service->updateAcademicPlacement($updated, $actor, ['current_academic_year_id' => $year->id, 'current_grade_id' => $grade->id, 'current_class_id' => $class->id]);
        $this->assertSame($class->id, $placed->current_class_id);

        $admitted = $service->transition($placed, $actor, LearnerStatus::Admitted, 'Accepted');
        $archived = $service->archive($admitted, $actor, 'Administrative archive');
        $restored = $service->restore($archived, $actor, 'Reopened');
        $this->assertSame(LearnerStatus::Admitted, $restored->learner_status);
        $this->assertDatabaseCount('learner_status_histories', 3);
        $this->assertDatabaseHas('audit_logs', ['action' => 'learners.created', 'target_id' => $learner->id]);
    }

    public function test_manual_number_requires_override_and_duplicate_rolls_back_profile_and_audits(): void
    {
        [$organization, $actor] = $this->context('manual');
        $service = app(LearnerService::class);

        try {
            $service->create($organization, $actor, ['first_name' => 'No', 'last_name' => 'Grant', 'learner_number' => 'MAN-1'], false);
            $this->fail('Expected manual number authorization to be enforced.');
        } catch (DomainException) {
            $this->assertDatabaseCount('learner_profiles', 0);
        }

        $service->create($organization, $actor, ['first_name' => 'Manual', 'last_name' => 'One', 'learner_number' => 'MAN-1'], true);
        try {
            $service->create($organization, $actor, ['first_name' => 'Manual', 'last_name' => 'Two', 'learner_number' => 'MAN-1'], true);
            $this->fail('Expected duplicate manual number rejection.');
        } catch (DomainException) {
            $this->assertDatabaseCount('learner_profiles', 1);
            $this->assertDatabaseCount('audit_logs', 2);
        }
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
