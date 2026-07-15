<?php

declare(strict_types=1);

namespace App\Application;

use Core\AuditLogs\Infrastructure\Models\AuditLog;
use Core\Identity\Domain\Enums\MembershipStatus;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Licensing\Infrastructure\Models\License;
use Core\Subscriptions\Infrastructure\Models\Subscription;
use Illuminate\Support\Collection;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Curriculum;
use Modules\Academics\Infrastructure\Models\Department;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Application\OrganizationService;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Staff\Infrastructure\Models\StaffProfile;

final class OrganizationDashboardService
{
    public function __construct(private readonly OrganizationService $organizations) {}

    /** @return array<string, mixed> */
    public function for(Membership $membership): array
    {
        $organization = $membership->getRelation('organization');
        abort_unless($organization instanceof Organization, 404);
        $organizationId = (string) $organization->getKey();

        $learners = LearnerProfile::query()->where('organization_id', $organizationId);
        $staff = StaffProfile::query()->where('organization_id', $organizationId);
        $academic = [
            'grades' => Grade::query()->where('organization_id', $organizationId)->count(),
            'classes' => ClassGroup::query()->where('organization_id', $organizationId)->count(),
            'subjects' => Subject::query()->where('organization_id', $organizationId)->count(),
            'departments' => Department::query()->where('organization_id', $organizationId)->count(),
            'curricula' => Curriculum::query()->where('organization_id', $organizationId)->count(),
        ];
        $currentYear = AcademicYear::query()->where('organization_id', $organizationId)->where('is_current', true)->value('name');
        $activeMemberships = Membership::query()->where('organization_id', $organizationId)->where('status', MembershipStatus::Active->value)->count();
        $pendingMemberships = Membership::query()->where('organization_id', $organizationId)->where('status', MembershipStatus::Invited->value)->count();
        $license = License::query()->where('licensee_type', Organization::class)->where('licensee_id', $organizationId)->latest()->first();
        $subscription = Subscription::query()->where('subscriber_type', Organization::class)->where('subscriber_id', $organizationId)->latest()->first();
        $maximumUsers = $subscription?->getAttribute('max_users') ?? $license?->getAttribute('max_users') ?? $organization->getAttribute('maximum_users');

        $people = [
            'learners_total' => (clone $learners)->count(),
            'learners_active' => (clone $learners)->where('learner_status', 'active')->count(),
            'learners_suspended' => (clone $learners)->where('learner_status', 'suspended')->count(),
            'learners_profile_only' => (clone $learners)->whereNull('user_id')->count(),
            'learners_portal_enabled' => (clone $learners)->where('portal_access_enabled', true)->count(),
            'staff_total' => (clone $staff)->count(),
            'staff_active' => (clone $staff)->where('employment_status', 'active')->count(),
            'staff_suspended' => (clone $staff)->where('employment_status', 'suspended')->count(),
        ];

        return [
            'branding' => $this->organizations->branding($organization),
            'organization' => ['name' => $organization->name, 'status' => $organization->status->value],
            'membership' => $membership,
            'people' => $people,
            'academics' => [...$academic, 'current_year' => $currentYear],
            'access' => [
                'active_memberships' => $activeMemberships,
                'pending_memberships' => $pendingMemberships,
                'maximum_users' => $maximumUsers,
                'remaining_users' => $maximumUsers === null ? null : max(0, (int) $maximumUsers - $activeMemberships),
                'license_status' => $license?->getAttribute('status')?->value,
                'subscription_status' => $subscription?->getAttribute('status')?->value,
                'grace_period_ends_at' => $subscription?->getAttribute('grace_period_ends_at')?->toDateString(),
            ],
            'setupGaps' => $this->setupGaps($organization, $people, $academic, $currentYear, $license, $subscription),
            'activity' => $this->activity($organizationId),
        ];
    }

    /** @return list<string> */
    private function setupGaps(Organization $organization, array $people, array $academic, ?string $currentYear, ?License $license, ?Subscription $subscription): array
    {
        $branding = $organization->settings()->where('group', 'branding')->exists();
        $checks = [
            'Organization branding has not been configured.' => ! $branding,
            'No current academic year is configured.' => $currentYear === null,
            'No curriculum is configured.' => $academic['curricula'] === 0,
            'No grades are configured.' => $academic['grades'] === 0,
            'No classes are configured.' => $academic['classes'] === 0,
            'No subjects are configured.' => $academic['subjects'] === 0,
            'No learners have been added.' => $people['learners_total'] === 0,
            'No staff have been added.' => $people['staff_total'] === 0,
            'No active license is assigned.' => $license?->getAttribute('status')?->value !== 'active',
            'No active subscription is assigned.' => ! in_array($subscription?->getAttribute('status')?->value, ['active', 'grace_period'], true),
            'AI provider is not configured for this organization.' => ! $organization->aiConfiguration()->exists(),
        ];

        return array_keys(array_filter($checks));
    }

    /** @return Collection<int, array{label: string, actor: string, occurred_at: string}> */
    private function activity(string $organizationId): Collection
    {
        /** @var Collection<int, AuditLog> $auditLogs */
        $auditLogs = AuditLog::query()->with('actor:id,name')->where('target_type', Organization::class)
            ->where('target_id', $organizationId)->latest()->limit(6)->get();
        $organizationAudits = $auditLogs->map(fn (AuditLog $log): array => [
            'label' => str_replace(['.', '_'], ' ', (string) $log->getAttribute('action')),
            'actor' => $log->getRelation('actor')?->getAttribute('name') ?? 'System',
            'occurred_at' => $log->getAttribute('created_at')->diffForHumans(),
        ]);
        /** @var Collection<int, LearnerProfile> $learners */
        $learners = LearnerProfile::query()->where('organization_id', $organizationId)->latest()->limit(3)->get();
        $learnerActivity = $learners->map(fn (LearnerProfile $learner): array => ['label' => 'Learner profile added', 'actor' => 'Organization user', 'occurred_at' => $learner->getAttribute('created_at')->diffForHumans()]);
        /** @var Collection<int, StaffProfile> $staff */
        $staff = StaffProfile::query()->where('organization_id', $organizationId)->latest()->limit(3)->get();
        $staffActivity = $staff->map(fn (StaffProfile $profile): array => ['label' => 'Staff profile added', 'actor' => 'Organization user', 'occurred_at' => $profile->getAttribute('created_at')->diffForHumans()]);

        return $organizationAudits->concat($learnerActivity)->concat($staffActivity)->take(8)->values();
    }
}
