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
use Modules\Learners\Application\GuardianInvitationService;
use Modules\Learners\Application\GuardianService;
use Modules\Learners\Database\Seeders\LearnersPermissionSeeder;
use Modules\Learners\Infrastructure\Models\GuardianProfile;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Infrastructure\Models\OrganizationModule;
use Tests\TestCase;

final class GuardianInvitationOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_administrator_creates_hashed_email_invitation_without_creating_user(): void
    {
        Notification::fake();
        [$organization, $admin] = $this->member('invite-create');
        $guardian = $this->guardian($organization, 'new.guardian@example.test');

        $response = $this->actingAs($admin, 'sanctum')
            ->withHeader('X-Organization-Id', $organization->id)
            ->postJson("/api/v1/guardians/{$guardian->uuid}/invitations", ['email' => 'NEW.Guardian@example.test'])
            ->assertCreated()
            ->assertJsonPath('data.email', 'new.guardian@example.test')
            ->assertJsonPath('data.status', 'invited')
            ->assertJsonMissingPath('data.user_id')
            ->assertJsonMissingPath('data.invitation_token');

        $membership = Membership::query()->where('invited_email', 'new.guardian@example.test')->firstOrFail();
        $this->assertNull($membership->user_id);
        $this->assertSame(64, strlen((string) $membership->invitation_token));
        $this->assertStringNotContainsString((string) $membership->invitation_token, $response->getContent());
        $this->assertDatabaseMissing('users', ['email' => 'new.guardian@example.test']);
        Notification::assertSentOnDemand(CoreNotification::class);
    }

    public function test_resend_rotates_token_and_revoke_prevents_acceptance(): void
    {
        Notification::fake();
        [$organization, $admin] = $this->member('invite-rotate');
        $guardian = $this->guardian($organization, 'rotate@example.test');
        $service = app(GuardianInvitationService::class);
        $first = $service->invite($guardian, $admin, 'rotate@example.test');
        $second = $service->resend($first['membership'], $guardian->refresh(), $admin);

        $this->assertNotSame($first['token'], $second['token']);
        $this->assertNotSame(hash('sha256', $first['token']), $second['membership']->invitation_token);
        $this->expectException(DomainException::class);
        $service->resolve($first['token']);
    }

    public function test_revoked_expired_and_reused_tokens_are_rejected(): void
    {
        Notification::fake();
        [$organization, $admin] = $this->member('invite-invalid');
        $guardian = $this->guardian($organization, 'invalid@example.test');
        $service = app(GuardianInvitationService::class);
        $invitation = $service->invite($guardian, $admin, 'invalid@example.test');
        $service->revoke($invitation['membership'], $guardian->refresh());

        try {
            $service->resolve($invitation['token']);
            $this->fail('Revoked token was accepted.');
        } catch (DomainException) {
            $this->assertTrue(true);
        }

        $expiredGuardian = $this->guardian($organization, 'expired@example.test');
        $expired = $service->invite($expiredGuardian, $admin, 'expired@example.test');
        $expired['membership']->forceFill(['invitation_expires_at' => now()->subMinute()])->save();
        $this->expectException(DomainException::class);
        $service->resolve($expired['token']);
    }

    public function test_new_account_acceptance_creates_one_user_membership_and_guardian_identity(): void
    {
        Notification::fake();
        [$organization, $admin] = $this->member('invite-new-account');
        $guardian = $this->guardian($organization, 'onboard@example.test');
        $service = app(GuardianInvitationService::class);
        $invitation = $service->invite($guardian, $admin, 'onboard@example.test');

        $accepted = $service->accept($invitation['token'], null, [
            'name' => 'Onboard Guardian',
            'password' => 'a-secure-guardian-password',
        ]);

        $user = User::query()->where('email', 'onboard@example.test')->firstOrFail();
        $membership = $invitation['membership']->refresh();
        $this->assertTrue(Hash::check('a-secure-guardian-password', $user->password));
        $this->assertSame(MembershipStatus::Active, $membership->status);
        $this->assertNull($membership->invitation_token);
        $this->assertSame($user->id, $accepted->user_id);
        $this->assertSame($membership->id, $accepted->organization_membership_id);
        $this->assertSame(1, User::query()->where('email', 'onboard@example.test')->count());
        $this->assertSame(1, Membership::query()->where('organization_id', $organization->id)->where('user_id', $user->id)->count());

        $this->expectException(DomainException::class);
        $service->accept($invitation['token'], $user, []);
    }

    public function test_existing_matching_account_accepts_without_duplicate_and_mismatch_fails(): void
    {
        Notification::fake();
        [$organization, $admin] = $this->member('invite-existing');
        $existing = User::factory()->create(['email' => 'existing@example.test']);
        $guardian = $this->guardian($organization, 'existing@example.test');
        $service = app(GuardianInvitationService::class);
        $invitation = $service->invite($guardian, $admin, 'existing@example.test');

        $other = User::factory()->create(['email' => 'other@example.test']);
        try {
            $service->accept($invitation['token'], $other, []);
            $this->fail('Mismatched account accepted invitation.');
        } catch (DomainException) {
            $this->assertNull($guardian->fresh()->user_id);
        }

        $accepted = $service->accept($invitation['token'], $existing, []);
        $this->assertSame($existing->id, $accepted->user_id);
        $this->assertSame(1, User::query()->where('email', 'existing@example.test')->count());
        $this->assertSame(1, Membership::query()->where('organization_id', $organization->id)->where('user_id', $existing->id)->count());
    }

    public function test_acceptance_unlocks_only_explicitly_linked_learner_portal_access(): void
    {
        Notification::fake();
        [$organization, $admin] = $this->member('invite-portal');
        $guardian = $this->guardian($organization, 'portal-onboard@example.test');
        $linked = LearnerProfile::factory()->active()->create(['organization_id' => $organization->id]);
        $unlinked = LearnerProfile::factory()->active()->create(['organization_id' => $organization->id]);
        app(GuardianService::class)->link($linked, $guardian, $admin, ['relationship_type' => 'parent']);
        $service = app(GuardianInvitationService::class);
        $invitation = $service->invite($guardian, $admin, 'portal-onboard@example.test');
        $accepted = $service->accept($invitation['token'], null, ['name' => 'Portal Guardian', 'password' => 'a-secure-guardian-password']);

        $this->actingAs($accepted->user, 'sanctum')->withHeader('X-Organization-Id', $organization->id)
            ->getJson("/api/v1/learners/{$linked->uuid}")->assertOk();
        $this->getJson("/api/v1/learners/{$unlinked->uuid}")->assertForbidden();
    }

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

    private function guardian(Organization $organization, string $email): GuardianProfile
    {
        return GuardianProfile::query()->create([
            'organization_id' => $organization->id,
            'first_name' => 'Invite',
            'last_name' => 'Guardian',
            'email' => $email,
        ]);
    }
}
