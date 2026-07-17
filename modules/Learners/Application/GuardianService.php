<?php

declare(strict_types=1);

namespace Modules\Learners\Application;

use Core\AuditLogs\Application\AuditLogService;
use Core\Identity\Domain\Enums\MembershipStatus;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Learners\Domain\Enums\GuardianStatus;
use Modules\Learners\Infrastructure\Models\GuardianProfile;
use Modules\Learners\Infrastructure\Models\LearnerConsent;
use Modules\Learners\Infrastructure\Models\LearnerGuardianRelationship;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;

final class GuardianService
{
    private const PROFILE_FIELDS = ['first_name', 'last_name', 'email', 'phone', 'preferred_communication_channel', 'address', 'status'];

    private const RELATIONSHIP_FIELDS = ['relationship_type', 'is_primary', 'is_emergency_contact', 'is_authorized_pickup', 'receives_academic_communication', 'receives_financial_communication', 'status', 'effective_from', 'effective_until'];

    public function __construct(private readonly AuditLogService $audit) {}

    public function create(Organization $organization, User $actor, array $data): GuardianProfile
    {
        return DB::transaction(function () use ($organization, $actor, $data): GuardianProfile {
            [$userId, $membershipId] = $this->identity($organization, $data['organization_membership_id'] ?? null);
            $guardian = GuardianProfile::query()->create([
                ...Arr::only($data, self::PROFILE_FIELDS),
                'organization_id' => $organization->getKey(),
                'user_id' => $userId,
                'organization_membership_id' => $membershipId,
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);
            $this->audit->record('guardians.created', $guardian, after: $this->auditValues($guardian));

            return $guardian;
        }, 3);
    }

    public function update(GuardianProfile $guardian, User $actor, array $data): GuardianProfile
    {
        return DB::transaction(function () use ($guardian, $actor, $data): GuardianProfile {
            $before = $this->auditValues($guardian);
            $guardian->fill(Arr::only($data, self::PROFILE_FIELDS));
            if (array_key_exists('organization_membership_id', $data)) {
                /** @var Organization $organization */
                $organization = $guardian->organization()->firstOrFail();
                [$userId, $membershipId] = $this->identity($organization, $data['organization_membership_id'], $guardian);
                $guardian->forceFill(['user_id' => $userId, 'organization_membership_id' => $membershipId]);
            }
            $guardian->setAttribute('updated_by', $actor->getKey())->save();
            $this->audit->record('guardians.updated', $guardian, $before, $this->auditValues($guardian));

            return $guardian->refresh();
        }, 3);
    }

    public function archive(GuardianProfile $guardian, User $actor): GuardianProfile
    {
        return DB::transaction(function () use ($guardian, $actor): GuardianProfile {
            $guardian->forceFill(['status' => GuardianStatus::Archived, 'archived_at' => now(), 'updated_by' => $actor->getKey()])->save();
            $guardian->relationships()->where('status', 'active')->update(['status' => 'inactive', 'is_primary' => false, 'updated_by' => $actor->getKey()]);
            $this->audit->record('guardians.archived', $guardian, after: ['status' => GuardianStatus::Archived->value]);

            return $guardian->refresh();
        }, 3);
    }

    public function link(LearnerProfile $learner, GuardianProfile $guardian, User $actor, array $data): LearnerGuardianRelationship
    {
        $this->sameOrganization($learner, $guardian);

        return DB::transaction(function () use ($learner, $guardian, $actor, $data): LearnerGuardianRelationship {
            LearnerProfile::query()->whereKey($learner->getKey())->lockForUpdate()->firstOrFail();
            $attributes = $this->normalizedRelationship($data);
            $this->assertGuardianMayReceive($guardian, (string) $attributes['status']);
            if ($attributes['status'] === 'active' && $attributes['is_primary'] === true) {
                $this->clearPrimary($learner, $actor);
            }

            /** @var LearnerGuardianRelationship|null $existing */
            $existing = LearnerGuardianRelationship::query()
                ->withoutGlobalScope(SoftDeletingScope::class)
                ->where('learner_profile_id', $learner->getKey())
                ->where('guardian_profile_id', $guardian->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing !== null && $existing->getAttribute('deleted_at') === null) {
                throw new DomainException('This guardian is already linked to the learner.');
            }

            try {
                if ($existing !== null) {
                    $existing->forceFill([
                        ...$attributes,
                        'organization_id' => $learner->getAttribute('organization_id'),
                        'learner_profile_id' => $learner->getKey(),
                        'guardian_profile_id' => $guardian->getKey(),
                        'created_by' => $actor->getKey(),
                        'updated_by' => $actor->getKey(),
                    ]);
                    $existing->restore();
                    $relationship = $existing;
                } else {
                    $relationship = LearnerGuardianRelationship::query()->create([
                        ...$attributes,
                        'organization_id' => $learner->getAttribute('organization_id'),
                        'learner_profile_id' => $learner->getKey(),
                        'guardian_profile_id' => $guardian->getKey(),
                        'created_by' => $actor->getKey(),
                        'updated_by' => $actor->getKey(),
                    ]);
                }
            } catch (QueryException $exception) {
                throw new DomainException('This guardian is already linked to the learner.', previous: $exception);
            }
            $this->audit->record('learners.guardian_linked', $learner, after: ['guardian_uuid' => $guardian->getAttribute('uuid'), 'relationship_type' => $relationship->getAttribute('relationship_type')]);

            return $relationship->load('guardian');
        }, 3);
    }

    public function updateRelationship(LearnerGuardianRelationship $relationship, User $actor, array $data): LearnerGuardianRelationship
    {
        return DB::transaction(function () use ($relationship, $actor, $data): LearnerGuardianRelationship {
            LearnerProfile::query()->whereKey($relationship->getAttribute('learner_profile_id'))->lockForUpdate()->firstOrFail();
            /** @var LearnerGuardianRelationship $locked */
            $locked = LearnerGuardianRelationship::query()->whereKey($relationship->getKey())->lockForUpdate()->firstOrFail();
            /** @var LearnerProfile $learner */
            $learner = $locked->learner()->firstOrFail();
            $attributes = $this->normalizedRelationship($data, $locked);
            $guardian = $locked->guardian()->firstOrFail();
            assert($guardian instanceof GuardianProfile);
            $this->assertGuardianMayReceive($guardian, (string) $attributes['status']);
            if ($attributes['status'] === 'active' && $attributes['is_primary'] === true) {
                $this->clearPrimary($learner, $actor, $locked);
            }
            $locked->fill($attributes)->setAttribute('updated_by', $actor->getKey())->save();
            $this->audit->record('learners.guardian_relationship_updated', $learner, after: Arr::only($locked->getAttributes(), self::RELATIONSHIP_FIELDS));

            return $locked->refresh()->load('guardian');
        }, 3);
    }

    public function unlink(LearnerGuardianRelationship $relationship, User $actor): void
    {
        DB::transaction(function () use ($relationship, $actor): void {
            $relationship->forceFill(['status' => 'inactive', 'is_primary' => false, 'updated_by' => $actor->getKey()])->save();
            /** @var LearnerProfile $learner */
            $learner = $relationship->learner()->firstOrFail();
            $this->audit->record('learners.guardian_unlinked', $learner, after: ['relationship_uuid' => $relationship->getAttribute('uuid')]);
            $relationship->delete();
        }, 3);
    }

    public function recordConsent(LearnerProfile $learner, ?GuardianProfile $guardian, User $actor, array $data): LearnerConsent
    {
        if ($guardian !== null) {
            $this->sameOrganization($learner, $guardian);
            if (! $learner->guardianRelationships()->where('guardian_profile_id', $guardian->getKey())->where('status', 'active')->exists()) {
                throw new DomainException('Consent may only reference a guardian currently linked to the learner.');
            }
        }

        return DB::transaction(function () use ($learner, $guardian, $actor, $data): LearnerConsent {
            $consent = LearnerConsent::query()->create([
                ...Arr::only($data, ['consent_type', 'status', 'recorded_date', 'expiry_date', 'notes']),
                'organization_id' => $learner->getAttribute('organization_id'),
                'learner_profile_id' => $learner->getKey(),
                'guardian_profile_id' => $guardian?->getKey(),
                'recorded_by' => $actor->getKey(),
            ]);
            $this->audit->record('learners.consent_recorded', $learner, after: ['consent_type' => $consent->getAttribute('consent_type'), 'status' => $consent->getAttribute('status')]);

            return $consent;
        }, 3);
    }

    private function clearPrimary(LearnerProfile $learner, User $actor, ?LearnerGuardianRelationship $except = null): void
    {
        $query = $learner->guardianRelationships()->where('is_primary', true)->where('status', 'active')->lockForUpdate();
        if ($except !== null) {
            $query->where($except->getQualifiedKeyName(), '!=', $except->getKey());
        }
        $query->update(['is_primary' => false, 'updated_by' => $actor->getKey()]);
    }

    private function sameOrganization(LearnerProfile $learner, GuardianProfile $guardian): void
    {
        if ($learner->getAttribute('organization_id') !== $guardian->getAttribute('organization_id')) {
            throw new DomainException('Learners and guardians must belong to the same organization.');
        }
    }

    private function identity(Organization $organization, mixed $membershipId, ?GuardianProfile $current = null): array
    {
        if ($membershipId === null || $membershipId === '') {
            return [null, null];
        }
        /** @var Membership $membership */
        $membership = Membership::query()->whereKey((string) $membershipId)->where('organization_id', $organization->getKey())->whereIn('status', [MembershipStatus::Active->value, MembershipStatus::Invited->value])->firstOrFail();
        $duplicate = GuardianProfile::query()
            ->where('organization_id', $organization->getKey())
            ->where('organization_membership_id', $membership->getKey())
            ->when($current !== null, fn ($query) => $query->where($current->getQualifiedKeyName(), '!=', $current->getKey()))
            ->exists();
        if ($duplicate) {
            throw new DomainException('That organization identity is already linked to a guardian.');
        }

        return [$membership->getAttribute('user_id'), $membership->getKey()];
    }

    private function auditValues(GuardianProfile $guardian): array
    {
        return Arr::only($guardian->getAttributes(), ['organization_id', 'status', 'preferred_communication_channel', 'organization_membership_id']);
    }

    private function assertGuardianMayReceive(GuardianProfile $guardian, string $relationshipStatus): void
    {
        if ($relationshipStatus === 'active' && ($guardian->getAttribute('status') !== GuardianStatus::Active || $guardian->getAttribute('archived_at') !== null || $guardian->getAttribute('deleted_at') !== null)) {
            throw new DomainException('Only active guardians may receive an active learner relationship.');
        }
    }

    private function normalizedRelationship(array $data, ?LearnerGuardianRelationship $current = null): array
    {
        $attributes = Arr::only($data, self::RELATIONSHIP_FIELDS);
        $status = (string) ($attributes['status'] ?? $current?->getAttribute('status') ?? 'active');
        $primary = (bool) ($attributes['is_primary'] ?? $current?->getAttribute('is_primary') ?? false);
        $attributes['status'] = $status;
        $attributes['is_primary'] = $status === 'active' && $primary;

        return $attributes;
    }
}
