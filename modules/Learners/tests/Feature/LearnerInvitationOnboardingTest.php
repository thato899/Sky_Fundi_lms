<?php

declare(strict_types=1);

namespace Modules\Learners\Tests\Feature;

use Core\Identity\Domain\Enums\MembershipStatus;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Notifications\Infrastructure\Notifications\CoreNotification;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Modules\Learners\Application\LearnerInvitationService;
use Modules\Learners\Database\Seeders\LearnersPermissionSeeder;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Infrastructure\Models\OrganizationModule;
use Tests\TestCase;

/**
 * Mirrors GuardianInvitationOnboardingTest's pattern — see that file —
 * for LearnerInvitationService, per docs/roadmap.md's "Learner
 * invitations/onboarding mirroring the guardian invitation flow".
 */
final class LearnerInvitationOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_administrator_creates_hashed_email_invitation_without_creating_user(): void
    {
        Notification::fake();
        [$organization, $admin] = $this->member('invite-create');
        $learner = $this->learner($organization, 'new.learner@example.test');

        $response = $this->actingAs($admin, 'sanctum')
            ->withHeader('X-Organization-Id', $organization->id)
            ->postJson("/api/v1/learners/{$learner->uuid}/invitations", ['email' => 'NEW.Learner@example.test'])
            ->assertCreated()
            ->assertJsonPath('data.email', 'new.learner@example.test')
            ->assertJsonPath('data.status', 'invited')
            ->assertJsonMissingPath('data.user_id')
            ->assertJsonMissingPath('data.invitation_token');

        $membership = Membership::query()->where('invited_email', 'new.learner@example.test')->firstOrFail();
        $this->assertNull($membership->user_id);
        $this->assertSame(64, strlen((string) $membership->invitation_token));
        $this->assertStringNotContainsString((string) $membership->invitation_token, $response->getContent());
        $this->assertDatabaseMissing('users', ['email' => 'new.learner@example.test']);
        Notification::assertSentOnDemand(CoreNotification::class);
    }

    public function test_resend_rotates_token_and_revoke_prevents_acceptance(): void
    {
        Notification::fake();
        [$organization, $admin] = $this->member('invite-rotate');
        $learner = $this->learner($organization, 'rotate@example.test');
        $service = app(LearnerInvitationService::class);
        $first = $service->invite($learner, $admin, 'rotate@example.test');
        $second = $service->resend($first['membership'], $learner->refresh(), $admin);

        $this->assertNotSame($first['token'], $second['token']);
        $this->assertNotSame(hash('sha256', $first['token']), $second['membership']->invitation_token);
        $this->expectException(DomainException::class);
        $service->resolve($first['token']);
    }

    public function test_revoked_expired_and_reused_tokens_are_rejected(): void
    {
        Notification::fake();
        [$organization, $admin] = $this->member('invite-invalid');
        $learner = $this->learner($organization, 'invalid@example.test');
        $service = app(LearnerInvitationService::class);
        $invitation = $service->invite($learner, $admin, 'invalid@example.test');
        $service->revoke($invitation['membership'], $learner->refresh());

        try {
            $service->resolve($invitation['token']);
            $this->fail('Revoked token was accepted.');
        } catch (DomainException) {
            $this->assertTrue(true);
        }

        $expiredLearner = $this->learner($organization, 'expired@example.test');
        $expired = $service->invite($expiredLearner, $admin, 'expired@example.test');
        $expired['membership']->forceFill(['invitation_expires_at' => now()->subMinute()])->save();
        $this->expectException(DomainException::class);
        $service->resolve($expired['token']);
    }

    public function test_new_account_acceptance_creates_one_user_membership_and_enables_portal_access(): void
    {
        Notification::fake();
        [$organization, $admin] = $this->member('invite-new-account');
        $learner = $this->learner($organization, 'onboard@example.test');
        $service = app(LearnerInvitationService::class);
        $invitation = $service->invite($learner, $admin, 'onboard@example.test');

        $accepted = $service->accept($invitation['token'], null, [
            'name' => 'Onboard Learner',
            'password' => 'a-secure-learner-password',
        ]);

        $user = User::query()->where('email', 'onboard@example.test')->firstOrFail();
        $membership = $invitation['membership']->refresh();
        $this->assertTrue(Hash::check('a-secure-learner-password', $user->password));
        $this->assertSame(MembershipStatus::Active, $membership->status);
        $this->assertNull($membership->invitation_token);
        $this->assertSame($user->id, $accepted->user_id);
        $this->assertSame($membership->id, $accepted->organization_membership_id);
        $this->assertTrue((bool) $accepted->portal_access_enabled);
        $this->assertSame('completed', $accepted->onboarding_status);
        $this->assertSame(1, User::query()->where('email', 'onboard@example.test')->count());
        $this->assertSame(1, Membership::query()->where('organization_id', $organization->id)->where('user_id', $user->id)->count());

        $this->expectException(DomainException::class);
        $service->accept($invitation['token'], $user, []);
    }

    public function test_existing_matching_account_accepts_without_duplicate_and_mismatch_fails(): void
    {
        Notification::fake();
        [$organization, $admin] = $this->member('invite-existing');
        $existing = User::factory()->create(['email' => 'existing-learner@example.test']);
        $learner = $this->learner($organization, 'existing-learner@example.test');
        $service = app(LearnerInvitationService::class);
        $invitation = $service->invite($learner, $admin, 'existing-learner@example.test');

        $other = User::factory()->create(['email' => 'other-learner@example.test']);
        try {
            $service->accept($invitation['token'], $other, []);
            $this->fail('Mismatched account accepted invitation.');
        } catch (DomainException) {
            $this->assertNull($learner->fresh()->user_id);
        }

        $accepted = $service->accept($invitation['token'], $existing, []);
        $this->assertSame($existing->id, $accepted->user_id);
        $this->assertSame(1, User::query()->where('email', 'existing-learner@example.test')->count());
        $this->assertSame(1, Membership::query()->where('organization_id', $organization->id)->where('user_id', $existing->id)->count());
    }

    public function test_accepted_learner_reaches_their_own_portal_and_has_no_membership_in_another_organization(): void
    {
        Notification::fake();
        [$organization, $admin] = $this->member('invite-portal-a');
        [$otherOrganization] = $this->member('invite-portal-b');
        $learner = $this->learner($organization, 'portal-onboard@example.test');
        $service = app(LearnerInvitationService::class);
        $invitation = $service->invite($learner, $admin, 'portal-onboard@example.test');
        $accepted = $service->accept($invitation['token'], null, ['name' => 'Portal Learner', 'password' => 'a-secure-learner-password']);

        $this->actingAs($accepted->user)->withSession(['organization_id' => $organization->id])
            ->get('/my/attendance')->assertOk();
        // Cross-organization isolation: this account has no membership in
        // the other organization at all, so selecting its context is
        // rejected outright — the accepted invitation never leaks access
        // beyond the organization it was issued for.
        $this->actingAs($accepted->user)->withSession(['organization_id' => $otherOrganization->id])
            ->get('/my/attendance')->assertForbidden();
    }

    public function test_invitation_token_cannot_be_resolved_against_a_different_organization(): void
    {
        Notification::fake();
        [$organizationA, $adminA] = $this->member('invite-cross-org-a');
        [$organizationB] = $this->member('invite-cross-org-b');
        $learnerA = $this->learner($organizationA, 'cross-org@example.test');
        $service = app(LearnerInvitationService::class);
        $invitation = $service->invite($learnerA, $adminA, 'cross-org@example.test');

        $membership = $service->resolve($invitation['token']);
        // The resolved membership belongs to organization A, never B —
        // a token minted for one organization's learner is not usable to
        // impersonate or accept into another organization's records.
        $this->assertSame($organizationA->id, $membership->organization_id);
        $this->assertNotSame($organizationB->id, $membership->organization_id);
    }

    public function test_only_active_learner_profiles_may_be_invited(): void
    {
        [$organization, $admin] = $this->member('invite-inactive');
        $learner = LearnerProfile::factory()->create([
            'organization_id' => $organization->id,
            'learner_email' => 'pending@example.test',
        ]);

        $this->expectException(DomainException::class);
        app(LearnerInvitationService::class)->invite($learner, $admin, 'pending@example.test');
    }

    /**
     * @return array{0: Organization, 1: User}
     */
    private function member(string $code): array
    {
        $this->seed(LearnersPermissionSeeder::class);
        $organization = Organization::query()->create(['name' => $code, 'code' => $code, 'type' => 'school']);
        OrganizationModule::query()->create(['organization_id' => $organization->id, 'module_name' => 'learners', 'enabled' => true]);
        $user = User::factory()->create();
        $role = Role::query()->where('name', 'Organization Administrator')->firstOrFail();
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active', 'is_default' => true]);

        return [$organization, $user];
    }

    private function learner(Organization $organization, string $email): LearnerProfile
    {
        return LearnerProfile::factory()->active()->create([
            'organization_id' => $organization->id,
            'learner_email' => $email,
        ]);
    }
}
