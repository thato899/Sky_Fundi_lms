<?php

declare(strict_types=1);

namespace App\Application;

use Core\Identity\Infrastructure\Models\Membership;
use Core\Licensing\Infrastructure\Models\License;
use Core\Subscriptions\Infrastructure\Models\Subscription;
use Modules\Assessments\Infrastructure\Models\AiGradingRequest;
use Modules\Assessments\Infrastructure\Models\Assessment;
use Modules\Assessments\Infrastructure\Models\QuizAttempt;
use Modules\Assessments\Infrastructure\Models\QuizStudyPlan;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Staff\Infrastructure\Models\StaffProfile;

final class HackathonSubscriptionService
{
    public function for(Organization $organization): array
    {
        $id = (string) $organization->getKey();
        $subscription = Subscription::query()->where('subscriber_type', Organization::class)->where('subscriber_id', $id)->latest()->first();
        $license = License::query()->where('licensee_type', Organization::class)->where('licensee_id', $id)->latest()->first();
        $planKey = strtolower((string) ($subscription?->getAttribute('plan') ?? 'growth'));
        $plan = config("hackathon.plans.{$planKey}", config('hackathon.plans.growth'));
        $ai = AiGradingRequest::query()->where('organization_id', $id)->where('status', 'completed')->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
        $aiCost = (float) (clone $ai)->sum('estimated_cost');
        $revenue = (float) (($subscription?->getAttribute('metadata')['monthly_price'] ?? null) ?? $plan['price']);
        $costs = $aiCost + (float) config('hackathon.profitability.notification_cost') + (float) config('hackathon.profitability.hosting_allocation') + (float) config('hackathon.profitability.support_allocation');
        $margin = $revenue - $costs;

        return [
            'subscription' => $subscription,
            'license' => $license,
            'plan' => $plan,
            'plans' => config('hackathon.plans'),
            'usage' => [
                'learners' => LearnerProfile::query()->where('organization_id', $id)->whereNull('archived_at')->count(),
                'staff' => StaffProfile::query()->where('organization_id', $id)->where('employment_status', 'active')->count(),
                'memberships' => Membership::query()->where('organization_id', $id)->where('status', 'active')->count(),
                'ai_requests' => (clone $ai)->count(),
                'ai_cost' => $aiCost,
            ],
            'financials' => ['revenue' => $revenue, 'variable_cost' => $costs, 'margin' => $margin, 'margin_percent' => $revenue > 0 ? $margin / $revenue * 100 : 0],
            'metrics' => [
                'active_schools' => 1,
                'active_learners' => LearnerProfile::query()->where('organization_id', $id)->where('learner_status', 'active')->count(),
                'published_quizzes' => Assessment::query()->where('organization_id', $id)->where('status', 'open')->whereHas('questions')->count(),
                'submissions' => QuizAttempt::query()->where('organization_id', $id)->whereNotNull('submitted_at')->count(),
                'study_plans' => QuizStudyPlan::query()->where('organization_id', $id)->count(),
            ],
        ];
    }
}
