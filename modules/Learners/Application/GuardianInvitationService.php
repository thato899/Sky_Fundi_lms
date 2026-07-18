<?php

declare(strict_types=1);

namespace Modules\Learners\Application;

use Core\AuditLogs\Application\AuditLogService;
use Core\Identity\Domain\Enums\MembershipStatus;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Notifications\Application\NotificationService;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Support\Exceptions\DomainException;
use Core\Users\Application\DTOs\CreateUserData;
use Core\Users\Application\UserService;
use Core\Users\Domain\Enums\UserStatus;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Learners\Domain\Enums\GuardianStatus;
use Modules\Learners\Infrastructure\Models\GuardianProfile;
use Modules\Organizations\Infrastructure\Models\Organization;

final class GuardianInvitationService
{
    private const EXPIRY_DAYS = 7;

    public function __construct(
        private readonly AuditLogService $audit,
        private readonly NotificationService $notifications,
        private readonly UserService $users,
    ) {}

    /** @return array{membership: Membership, token: string} */
    public function invite(GuardianProfile $guardian, User $actor, string $email): array
    {
        $email = Str::lower(trim($email));
        $this->assertGuardianIsInvitable($guardian);

        $result = DB::transaction(function () use ($guardian, $actor, $email): array {
            /** @var GuardianProfile $lockedGuardian */
            $lockedGuardian = GuardianProfile::query()->whereKey($guardian->getKey())->lockForUpdate()->firstOrFail();
            $this->assertGuardianIsInvitable($lockedGuardian);

            /** @var Membership|null $existing */
            $existing = $lockedGuardian->organization_membership_id === null
                ? null
                : Membership::query()->whereKey($lockedGuardian->organization_membership_id)->lockForUpdate()->first();
            if ($existing instanceof Membership && $existing->status === MembershipStatus::Invited && ! $existing->invitation_expires_at?->isPast()) {
                if (Str::lower((string) $existing->invited_email) !== $email) {
                    throw new DomainException('Revoke the pending invitation before changing its email address.');
                }

                return $this->rotateLocked($existing, $lockedGuardian, $actor, false);
            }

            if ($existing instanceof Membership && $existing->status === MembershipStatus::Active) {
                throw new DomainException('This guardian already has active portal access.');
            }

            /** @var User|null $user */
            $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();
            /** @var Membership|null $membership */
            $membership = Membership::query()
                ->where('organization_id', $lockedGuardian->organization_id)
                ->where(function ($query) use ($email, $user): void {
                    $query->whereRaw('lower(invited_email) = ?', [$email]);
                    if ($user !== null) {
                        $query->orWhere('user_id', $user->getKey());
                    }
                })
                ->lockForUpdate()
                ->first();

            if ($membership instanceof Membership && $membership->status === MembershipStatus::Active) {
                throw new DomainException('This email already has active organization access. Link that membership to the guardian instead.');
            }

            /** @var Role $role */
            $role = Role::query()->where('name', 'Guardian')->firstOrFail();
            $membership ??= new Membership;
            $membership->fill([
                'user_id' => $user?->getKey(),
                'organization_id' => $lockedGuardian->organization_id,
                'role_id' => $role->getKey(),
                'status' => MembershipStatus::Invited,
                'invited_by' => $actor->getKey(),
                'invited_email' => $email,
            ]);

            return $this->rotateLocked($membership, $lockedGuardian, $actor, true);
        }, 3);

        $this->send($result['membership'], $guardian, $result['token']);

        return $result;
    }

    /** @return array{membership: Membership, token: string} */
    public function resend(Membership $membership, GuardianProfile $guardian, User $actor): array
    {
        $result = DB::transaction(function () use ($membership, $guardian, $actor): array {
            /** @var Membership $locked */
            $locked = Membership::query()->whereKey($membership->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== MembershipStatus::Invited) {
                throw new DomainException('Only pending invitations may be resent.');
            }

            return $this->rotateLocked($locked, $guardian, $actor, false);
        }, 3);

        $this->send($result['membership'], $guardian, $result['token']);

        return $result;
    }

    public function revoke(Membership $membership, GuardianProfile $guardian): Membership
    {
        return DB::transaction(function () use ($membership, $guardian): Membership {
            /** @var Membership $locked */
            $locked = Membership::query()->whereKey($membership->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== MembershipStatus::Invited) {
                throw new DomainException('Only pending invitations may be revoked.');
            }
            $locked->forceFill([
                'status' => MembershipStatus::Revoked,
                'invitation_token' => null,
                'revoked_at' => now(),
            ])->save();
            $this->audit->record('guardians.invitation_revoked', $guardian, after: ['email' => $locked->invited_email]);

            return $locked->refresh();
        }, 3);
    }

    public function resolve(string $token, bool $lock = false): Membership
    {
        $query = Membership::query()->where('invitation_token', hash('sha256', $token));
        if ($lock) {
            $query->lockForUpdate();
        }
        /** @var Membership|null $membership */
        $membership = $query->first();
        if (! $membership instanceof Membership) {
            throw new DomainException('This invitation is not valid.');
        }
        if ($membership->status !== MembershipStatus::Invited) {
            throw new DomainException('This invitation is no longer available.');
        }
        if ($membership->invitation_expires_at === null || $membership->invitation_expires_at->isPast()) {
            $membership->forceFill(['status' => MembershipStatus::Expired, 'invitation_token' => null])->save();
            throw new DomainException('This invitation has expired.');
        }

        return $membership;
    }

    public function accept(string $token, ?User $authenticatedUser, array $data): GuardianProfile
    {
        return DB::transaction(function () use ($token, $authenticatedUser, $data): GuardianProfile {
            $membership = $this->resolve($token, true);
            /** @var GuardianProfile|null $guardian */
            $guardian = GuardianProfile::query()
                ->where('organization_id', $membership->organization_id)
                ->where('organization_membership_id', $membership->getKey())
                ->lockForUpdate()
                ->first();
            if (! $guardian instanceof GuardianProfile) {
                throw new DomainException('This invitation is not valid.');
            }
            $this->assertGuardianIsInvitable($guardian);
            $email = Str::lower((string) $membership->invited_email);
            $user = $authenticatedUser;

            if ($user !== null && Str::lower($user->email) !== $email) {
                throw new DomainException('Sign in with the email address that received this invitation.');
            }
            if ($user === null) {
                /** @var User|null $user */
                $user = User::query()->whereRaw('lower(email) = ?', [$email])->lockForUpdate()->first();
                if ($user !== null) {
                    throw new DomainException('Sign in to the existing account before accepting this invitation.');
                }
                $user = $this->users->create(new CreateUserData(
                    name: trim((string) $data['name']),
                    email: $email,
                    password: (string) $data['password'],
                ));
                $user->forceFill(['email_verified_at' => now(), 'status' => UserStatus::Active])->save();
            }

            /** @var Membership|null $duplicate */
            $duplicate = Membership::query()
                ->where('organization_id', $membership->organization_id)
                ->where('user_id', $user->getKey())
                ->whereKeyNot($membership->getKey())
                ->lockForUpdate()
                ->first();
            if ($duplicate instanceof Membership) {
                if ($duplicate->status !== MembershipStatus::Active) {
                    throw new DomainException('This account already has a conflicting organization membership.');
                }
                $membership->delete();
                $membership = $duplicate;
            } else {
                try {
                    $membership->forceFill([
                        'user_id' => $user->getKey(),
                        'status' => MembershipStatus::Active,
                        'accepted_at' => now(),
                        'joined_at' => now(),
                        'invitation_token' => null,
                        'is_default' => ! Membership::query()->where('user_id', $user->getKey())->where('status', MembershipStatus::Active->value)->exists(),
                    ])->save();
                } catch (QueryException $exception) {
                    throw new DomainException('This invitation has already been accepted.', previous: $exception);
                }
            }

            $this->linkIdentity($guardian, $membership, $user);
            $this->audit->record('guardians.invitation_accepted', $guardian, after: ['email' => $email]);
            $this->audit->record('guardians.identity_linked', $guardian, after: ['membership_status' => 'active']);

            return $guardian->refresh();
        }, 3);
    }

    /** @return array{membership: Membership, token: string} */
    private function rotateLocked(Membership $membership, GuardianProfile $guardian, User $actor, bool $created): array
    {
        $token = Str::random(64);
        $membership->forceFill([
            'status' => MembershipStatus::Invited,
            'invitation_token' => hash('sha256', $token),
            'invitation_expires_at' => now()->addDays(self::EXPIRY_DAYS),
            'invitation_sent_at' => now(),
            'revoked_at' => null,
            'accepted_at' => null,
            'resend_count' => $created ? 0 : ((int) $membership->resend_count + 1),
        ])->save();
        $guardian->forceFill([
            'email' => $membership->invited_email,
            'organization_membership_id' => $membership->getKey(),
            'user_id' => null,
            'updated_by' => $actor->getKey(),
        ])->save();
        $this->audit->record(
            $created ? 'guardians.invitation_created' : 'guardians.invitation_resent',
            $guardian,
            after: ['email' => $membership->invited_email, 'expires_at' => $membership->invitation_expires_at?->toIso8601String()],
        );

        return ['membership' => $membership->refresh(), 'token' => $token];
    }

    private function send(Membership $membership, GuardianProfile $guardian, string $token): void
    {
        /** @var Organization $organization */
        $organization = $guardian->organization()->firstOrFail();
        $this->notifications->sendToEmail((string) $membership->invited_email, 'Guardian portal invitation', [
            'message' => "{$organization->name} invited you to its guardian portal. This link expires {$membership->invitation_expires_at?->toDayDateTimeString()}. No learner information is included in this email.",
            'action_text' => 'Review invitation',
            'action_url' => route('guardian-invitations.show', ['token' => $token]),
        ]);
    }

    private function linkIdentity(GuardianProfile $guardian, Membership $membership, ?User $user): void
    {
        $duplicate = GuardianProfile::query()
            ->where('organization_id', $guardian->organization_id)
            ->where('organization_membership_id', $membership->getKey())
            ->whereKeyNot($guardian->getKey())
            ->exists();
        if ($duplicate) {
            throw new DomainException('This organization membership is already linked to another guardian.');
        }
        $guardian->forceFill([
            'organization_membership_id' => $membership->getKey(),
            'user_id' => $user?->getKey(),
        ])->save();
    }

    private function assertGuardianIsInvitable(GuardianProfile $guardian): void
    {
        if ($guardian->status !== GuardianStatus::Active || $guardian->archived_at !== null || $guardian->deleted_at !== null) {
            throw new DomainException('Only active guardian profiles may be invited.');
        }
    }
}
