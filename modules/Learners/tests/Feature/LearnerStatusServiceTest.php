<?php

declare(strict_types=1);

namespace Modules\Learners\Tests\Feature;

use Core\Identity\Infrastructure\Models\Membership;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use LogicException;
use Modules\Learners\Application\LearnerStatusService;
use Modules\Learners\Domain\Enums\LearnerStatus;
use Modules\Learners\Events\LearnerArchived;
use Modules\Learners\Events\LearnerRestored;
use Modules\Learners\Events\LearnerStatusChanged;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Learners\Infrastructure\Models\LearnerStatusHistory;
use Modules\Organizations\Infrastructure\Models\Organization;
use Tests\TestCase;

final class LearnerStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_transition_records_history_and_dispatches_event(): void
    {
        Event::fake();
        [$organization, $actor] = $this->organizationAndActor('valid');
        $learner = LearnerProfile::factory()->create(['organization_id' => $organization->id]);

        $result = app(LearnerStatusService::class)->transition($learner, LearnerStatus::Admitted, $actor, 'Application accepted');

        $this->assertSame(LearnerStatus::Admitted, $result->learner_status);
        $this->assertDatabaseHas('learner_status_histories', [
            'organization_id' => $organization->id,
            'learner_profile_id' => $learner->id,
            'previous_status' => 'pending',
            'new_status' => 'admitted',
            'actor_id' => $actor->id,
            'reason' => 'Application accepted',
        ]);
        $this->assertNotNull(LearnerStatusHistory::firstOrFail()->changed_at);
        Event::assertDispatched(LearnerStatusChanged::class, fn (LearnerStatusChanged $event) => $event->previousStatus === LearnerStatus::Pending && $event->newStatus === LearnerStatus::Admitted);
    }

    public function test_invalid_and_no_op_transitions_are_rejected_without_history(): void
    {
        [$organization, $actor] = $this->organizationAndActor('invalid');
        $learner = LearnerProfile::factory()->create(['organization_id' => $organization->id]);
        $service = app(LearnerStatusService::class);

        foreach ([LearnerStatus::Pending, LearnerStatus::Completed] as $status) {
            try {
                $service->transition($learner, $status, $actor);
                $this->fail('Expected the transition to be rejected.');
            } catch (DomainException) {
                $this->assertSame(LearnerStatus::Pending, $learner->fresh()->learner_status);
            }
        }

        $this->assertDatabaseCount('learner_status_histories', 0);
    }

    public function test_archival_and_restoration_preserve_history_and_restore_previous_status(): void
    {
        Event::fake();
        [$organization, $actor] = $this->organizationAndActor('archive');
        $learner = LearnerProfile::factory()->active()->create(['organization_id' => $organization->id]);
        $service = app(LearnerStatusService::class);

        $archived = $service->archive($learner, $actor, 'Left the school');
        $this->assertSame(LearnerStatus::Archived, $archived->learner_status);
        $this->assertNotNull($archived->archived_at);

        $restored = $service->restore($archived, $actor, 'Returned');
        $this->assertSame(LearnerStatus::Active, $restored->learner_status);
        $this->assertNull($restored->archived_at);
        $this->assertSame(
            [['active', 'archived'], ['archived', 'active']],
            $restored->statusHistory()->orderBy('changed_at')->get()->map(fn (LearnerStatusHistory $history) => [$history->previous_status->value, $history->new_status->value])->all(),
        );
        Event::assertDispatched(LearnerArchived::class);
        Event::assertDispatched(LearnerRestored::class);
    }

    public function test_restore_is_rejected_for_non_archived_learner(): void
    {
        [$organization, $actor] = $this->organizationAndActor('not-archived');
        $learner = LearnerProfile::factory()->active()->create(['organization_id' => $organization->id]);

        $this->expectException(DomainException::class);
        app(LearnerStatusService::class)->restore($learner, $actor);
    }

    public function test_actor_from_another_organization_is_rejected(): void
    {
        [$organization] = $this->organizationAndActor('learner');
        [, $otherActor] = $this->organizationAndActor('other');
        $learner = LearnerProfile::factory()->create(['organization_id' => $organization->id]);

        $this->expectException(DomainException::class);
        app(LearnerStatusService::class)->transition($learner, LearnerStatus::Admitted, $otherActor);
    }

    public function test_status_history_cannot_be_updated_or_deleted_through_the_model(): void
    {
        [$organization, $actor] = $this->organizationAndActor('immutable');
        $learner = LearnerProfile::factory()->create(['organization_id' => $organization->id]);
        app(LearnerStatusService::class)->transition($learner, LearnerStatus::Admitted, $actor);
        $history = LearnerStatusHistory::firstOrFail();

        try {
            $history->update(['reason' => 'changed']);
            $this->fail('Expected history updates to be rejected.');
        } catch (LogicException) {
            $this->assertNull($history->fresh()->reason);
        }

        $this->expectException(LogicException::class);
        $history->delete();
    }

    /** @return array{Organization, User} */
    private function organizationAndActor(string $suffix): array
    {
        $organization = Organization::create(['name' => "School {$suffix}", 'code' => "school-{$suffix}", 'type' => 'school']);
        $actor = User::factory()->create();
        Membership::create(['organization_id' => $organization->id, 'user_id' => $actor->id]);

        return [$organization, $actor];
    }
}
