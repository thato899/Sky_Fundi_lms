<?php

declare(strict_types=1);

namespace Modules\Leaderboards\Application;

use Core\AuditLogs\Application\AuditLogService;
use Core\Notifications\Application\NotificationService;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Modules\Leaderboards\Infrastructure\Models\SportspersonOfTheWeek;
use Modules\Learners\Infrastructure\Models\GuardianProfile;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;

/**
 * The Sportsperson of the Week spotlight — a principal-authored post,
 * one per calendar week per organization, that names a learner and a
 * short citation. Draft until published; publishing notifies the
 * learner and their guardians the same way a report-card publication
 * does (see Modules\Reports\Application\ReportCardService).
 */
final class SportspersonService
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @param  array{learner_profile_id: string, week_start_date: string, citation: string}  $data
     */
    public function post(Organization $organization, User $actor, array $data): SportspersonOfTheWeek
    {
        $learner = LearnerProfile::query()->where('organization_id', $organization->getKey())->find($data['learner_profile_id'] ?? null);
        if (! $learner instanceof LearnerProfile) {
            throw new DomainException('The learner must belong to the active organization.');
        }
        $citation = trim((string) ($data['citation'] ?? ''));
        if ($citation === '') {
            throw new DomainException('A citation is required.');
        }
        if (SportspersonOfTheWeek::query()->where('organization_id', $organization->getKey())->where('week_start_date', $data['week_start_date'] ?? null)->exists()) {
            throw new DomainException('A Sportsperson of the Week has already been posted for that week.');
        }

        $post = SportspersonOfTheWeek::query()->create([
            'organization_id' => $organization->getKey(),
            'learner_profile_id' => $learner->getKey(),
            'week_start_date' => $data['week_start_date'],
            'citation' => $citation,
            'posted_by' => $actor->getKey(),
            'is_published' => false,
        ]);
        $this->audit->record('sportsperson_of_the_week.posted', $post, after: ['organization_id' => $organization->getKey(), 'learner_profile_id' => $learner->getKey()]);

        return $post;
    }

    public function publish(SportspersonOfTheWeek $post, User $actor): SportspersonOfTheWeek
    {
        if ($post->is_published) {
            return $post;
        }
        $post->update(['is_published' => true, 'published_at' => now()]);
        $this->audit->record('sportsperson_of_the_week.published', $post, after: ['organization_id' => $post->organization_id]);

        $post->loadMissing('learner.user', 'learner.guardianRelationships.guardian.user');
        $data = ['message' => 'A new Sportsperson of the Week has been announced.', 'citation' => $post->citation];
        if ($post->learner->user !== null) {
            $this->notifications->send($post->learner->user, 'leaderboards.sportsperson_of_the_week', $data);
        }
        $post->learner->guardianRelationships
            ->filter(fn ($relationship) => $relationship->status === 'active' && $relationship->deleted_at === null)
            ->map(fn ($relationship) => $relationship->guardian)
            ->filter(fn (GuardianProfile $guardian) => $guardian->user !== null && $guardian->status->value === 'active' && $guardian->archived_at === null)
            ->unique('user_id')
            ->each(fn (GuardianProfile $guardian) => $this->notifications->send($guardian->user, 'leaderboards.sportsperson_of_the_week', $data));

        return $post->refresh();
    }
}
