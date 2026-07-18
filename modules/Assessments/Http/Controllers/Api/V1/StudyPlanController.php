<?php

declare(strict_types=1);

namespace Modules\Assessments\Http\Controllers\Api\V1;

use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Assessments\Application\StudyPlanService;
use Modules\Assessments\Infrastructure\Models\QuizAttempt;
use Modules\Assessments\Infrastructure\Models\QuizStudyPlan;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;

final class StudyPlanController
{
    public function __construct(private readonly StudyPlanService $studyPlans, private readonly PermissionResolver $permissions) {}

    public function show(Request $request, string $plan): JsonResponse
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'quiz_attempts.submit'), 403);
        $learner = $this->learner($request, $organization);
        $studyPlan = QuizStudyPlan::query()->where('organization_id', $organization->getKey())->where('learner_profile_id', $learner->getKey())->where('uuid', $plan)->where('status', 'published')->with('revisionAttempts')->firstOrFail();

        return response()->json(['data' => $this->learnerProjection($studyPlan)]);
    }

    public function progress(Request $request, string $plan): JsonResponse
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'quiz_attempts.submit'), 403);
        $learner = $this->learner($request, $organization);
        $studyPlan = QuizStudyPlan::query()->where('organization_id', $organization->getKey())->where('learner_profile_id', $learner->getKey())->where('uuid', $plan)->firstOrFail();
        $data = $request->validate(['completed_activity_ids' => ['required', 'array', 'max:100'], 'completed_activity_ids.*' => ['required', 'string', 'max:100'], 'time_spent_minutes' => ['required', 'integer', 'min:1', 'max:1440']]);

        return response()->json(['data' => $this->learnerProjection($this->studyPlans->recordProgress($studyPlan, $learner, $data['completed_activity_ids'], $data['time_spent_minutes']))]);
    }

    public function retest(Request $request, string $plan): JsonResponse
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'quiz_attempts.submit'), 403);
        $learner = $this->learner($request, $organization);
        $studyPlan = QuizStudyPlan::query()->where('organization_id', $organization->getKey())->where('learner_profile_id', $learner->getKey())->where('uuid', $plan)->firstOrFail();
        $data = $request->validate(['responses' => ['required', 'array', 'min:1', 'max:30'], 'responses.*' => ['required', 'string', 'max:5000']]);
        try {
            $revision = $this->studyPlans->retest($studyPlan, $learner, $data['responses']);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => ['uuid' => $revision->uuid, 'status' => $revision->status, 'score_percentage' => $revision->score_percentage, 'evaluation' => $revision->evaluation]]);
    }

    public function regenerate(Request $request, string $attempt): JsonResponse
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'study_plans.generate'), 403);
        $quizAttempt = QuizAttempt::query()->where('organization_id', $organization->getKey())->where('uuid', $attempt)->with('assessment')->firstOrFail();
        $plan = $this->studyPlans->generate($quizAttempt, $this->actor($request), regenerate: true);

        return response()->json(['data' => ['uuid' => $plan->uuid, 'version' => $plan->version, 'status' => $plan->status]], 201);
    }

    public function publish(Request $request, string $plan): JsonResponse
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'study_plans.approve'), 403);
        $studyPlan = QuizStudyPlan::query()->where('organization_id', $organization->getKey())->where('uuid', $plan)->firstOrFail();
        $published = $this->studyPlans->publish($studyPlan, $this->actor($request));

        return response()->json(['data' => ['uuid' => $published->uuid, 'version' => $published->version, 'status' => $published->status]]);
    }

    public function analytics(Request $request): JsonResponse
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'study_plans.analytics'), 403);

        return response()->json(['data' => $this->studyPlans->analytics($organization->getKey())]);
    }

    private function learnerProjection(QuizStudyPlan $plan): array
    {
        return [
            'uuid' => $plan->uuid,
            'version' => $plan->version,
            'content' => $plan->content,
            'completion_percentage' => $plan->completion_percentage,
            'time_spent_minutes' => $plan->time_spent_minutes,
            'completed_activities' => $plan->completed_activities ?? [],
            'mastered_concepts' => $plan->mastered_concepts ?? [],
            'remaining_concepts' => $plan->remaining_concepts ?? [],
            'last_activity_at' => $plan->last_activity_at?->toIso8601String(),
        ];
    }

    private function learner(Request $request, Organization $organization): LearnerProfile
    {
        return LearnerProfile::query()->where('organization_id', $organization->getKey())->where('user_id', $request->user()?->getAuthIdentifier())->firstOrFail();
    }

    private function context(Request $request): array
    {
        $organization = $request->attributes->get('organization');
        $membership = $request->attributes->get('organization_membership');
        abort_unless($organization instanceof Organization && $membership instanceof Membership, 403);

        return [$organization, $membership];
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}
