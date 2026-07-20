<?php

declare(strict_types=1);

namespace Modules\Assessments\Http\Controllers\Web;

use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Assessments\Application\InterventionDashboardService;
use Modules\Assessments\Application\QuizService;
use Modules\Assessments\Application\StudyPlanService;
use Modules\Assessments\Infrastructure\Models\Assessment;
use Modules\Assessments\Infrastructure\Models\QuizAnswer;
use Modules\Assessments\Infrastructure\Models\QuizAttempt;
use Modules\Assessments\Infrastructure\Models\QuizStudyPlan;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Application\OrganizationService;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Staff\Application\TeachingAssignmentService;

final class QuizWebController
{
    public function __construct(private readonly QuizService $quizzes, private readonly StudyPlanService $studyPlans, private readonly InterventionDashboardService $interventions, private readonly PermissionResolver $permissions, private readonly OrganizationService $organizations, private readonly TeachingAssignmentService $assignments) {}

    public function show(Request $request, Assessment $assessment): View
    {
        [$organization, $membership] = $this->context($request);
        Gate::authorize('view', $assessment);

        return view('quizzes.show', $this->shared($organization, $membership) + ['quiz' => $assessment->load(['questions.options', 'subject', 'classGroup', 'attempts.learner'])]);
    }

    public function addQuestion(Request $request, Assessment $assessment): RedirectResponse
    {
        Gate::authorize('update', $assessment);
        $request->merge(['options' => collect($request->input('options', []))->filter(fn ($option) => is_array($option) && trim((string) ($option['label'] ?? '')) !== '')->values()->all()]);
        $data = $request->validate([
            'type' => ['required', 'in:multiple_choice,true_false,short_response,long_response'],
            'prompt' => ['required', 'string', 'max:5000'],
            'marks_available' => ['required', 'numeric', 'gt:0', 'max:1000'],
            'model_answer' => ['nullable', 'string', 'max:5000'],
            'marking_guidance' => ['nullable', 'string', 'max:5000'],
            'key_concepts' => ['nullable', 'array', 'max:12'],
            'key_concepts.*' => ['nullable', 'string', 'max:150'],
            'options' => ['nullable', 'array', 'max:8'],
            'options.*.label' => ['required_with:options', 'string', 'max:500'],
            'options.*.is_correct' => ['nullable', 'boolean'],
        ]);
        try {
            $this->quizzes->addQuestion($assessment, $this->actor($request), $data);
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['question' => $exception->getMessage()]);
        }

        return back()->with('status', 'Question added and total marks recalculated.');
    }

    public function publish(Request $request, Assessment $assessment): RedirectResponse
    {
        Gate::authorize('update', $assessment);
        try {
            $this->quizzes->publish($assessment, $this->actor($request));
        } catch (DomainException $exception) {
            return back()->withErrors(['publish' => $exception->getMessage()]);
        }

        return back()->with('status', 'Quiz published to its assigned class.');
    }

    public function assigned(Request $request): View
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'quiz_attempts.submit'), 403);
        $learner = $this->learner($request, $organization);
        $quizzes = Assessment::query()->where('organization_id', $organization->getKey())->where('status', 'open')->whereHas('results', fn ($query) => $query->where('learner_profile_id', $learner->getKey()))->with(['subject', 'attempts' => fn ($query) => $query->where('learner_profile_id', $learner->getKey())])->latest()->get();
        $plans = QuizStudyPlan::query()->where('organization_id', $organization->getKey())->where('learner_profile_id', $learner->getKey())->where('status', 'published')->latest('published_at')->get();
        $dashboard = [
            'current_mastery' => $plans->sum(fn (QuizStudyPlan $plan) => count($plan->mastered_concepts ?? [])),
            'study_streak' => $plans->whereNotNull('last_activity_at')->groupBy(fn (QuizStudyPlan $plan) => $plan->last_activity_at->toDateString())->count(),
            'weakest_topics' => $plans->flatMap(fn (QuizStudyPlan $plan) => $plan->remaining_concepts ?? [])->countBy()->sortDesc()->keys()->take(3),
            'recommended_next_lesson' => $plans->first()?->content['daily_schedule'][0]['topic'] ?? null,
            'readiness' => $plans->isNotEmpty() && $plans->every(fn (QuizStudyPlan $plan) => empty($plan->remaining_concepts)) ? 'Ready' : 'Building',
            'average_progress' => round((float) $plans->avg('completion_percentage')),
        ];

        return view('quizzes.assigned', $this->shared($organization, $membership) + compact('learner', 'quizzes', 'dashboard'));
    }

    public function start(Request $request, Assessment $assessment): RedirectResponse
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'quiz_attempts.submit'), 403);
        try {
            $attempt = $this->quizzes->start($assessment, $this->learner($request, $organization));
        } catch (DomainException $exception) {
            return back()->withErrors(['attempt' => $exception->getMessage()]);
        }

        return redirect()->route('quizzes.attempt', $attempt->uuid);
    }

    public function attempt(Request $request, string $attempt): View
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'quiz_attempts.submit'), 403);
        $learner = $this->learner($request, $organization);
        $quizAttempt = QuizAttempt::query()->where('organization_id', $organization->getKey())->where('learner_profile_id', $learner->getKey())->where('uuid', $attempt)->with(['assessment.subject', 'answers.question.options', 'result', 'publishedStudyPlan.revisionAttempts'])->firstOrFail();
        if ($quizAttempt->status === 'released') {
            return view('quizzes.result', $this->shared($organization, $membership) + compact('learner', 'quizAttempt'));
        }

        return view('quizzes.attempt', $this->shared($organization, $membership) + compact('learner', 'quizAttempt'));
    }

    public function submit(Request $request, string $attempt): RedirectResponse
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'quiz_attempts.submit'), 403);
        $learner = $this->learner($request, $organization);
        $quizAttempt = QuizAttempt::query()->where('organization_id', $organization->getKey())->where('learner_profile_id', $learner->getKey())->where('uuid', $attempt)->firstOrFail();
        $data = $request->validate(['answers' => ['required', 'array'], 'answers.*.selected_option_uuid' => ['nullable', 'uuid'], 'answers.*.answer_text' => ['nullable', 'string', 'max:20000']]);
        try {
            $this->quizzes->submit($quizAttempt, $learner, $data['answers']);
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['submit' => $exception->getMessage()]);
        }

        return redirect()->route('quizzes.assigned')->with('status', 'Quiz submitted. Written answers are awaiting teacher review.');
    }

    public function review(Request $request, string $attempt): View
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'quiz_submissions.mark'), 403);
        $quizAttempt = QuizAttempt::query()->where('organization_id', $organization->getKey())->where('uuid', $attempt)->with(['assessment.subject', 'learner.currentGrade', 'answers.question.options', 'studyPlan.revisionAttempts', 'publishedStudyPlan'])->firstOrFail();
        $this->authorizeAssignment($membership, $quizAttempt);

        return view('quizzes.review', $this->shared($organization, $membership) + compact('quizAttempt'));
    }

    public function suggest(Request $request, string $attempt, string $answer): RedirectResponse
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'quiz_submissions.mark'), 403);
        $quizAttempt = QuizAttempt::query()->where('organization_id', $organization->getKey())->where('uuid', $attempt)->firstOrFail();
        $this->authorizeAssignment($membership, $quizAttempt);
        $quizAnswer = QuizAnswer::query()->where('quiz_attempt_id', $quizAttempt->getKey())->where('uuid', $answer)->firstOrFail();
        try {
            $this->quizzes->suggestWrittenMark(
                $quizAnswer,
                $this->actor($request),
                $request->boolean('regenerate'),
                $this->permissions->allows($membership, 'quiz_submissions.override_released'),
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['ai' => $exception->getMessage()]);
        }

        return back()->with('status', 'AI suggestion ready for teacher review.');
    }

    public function saveReview(Request $request, string $attempt): RedirectResponse
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'quiz_submissions.mark'), 403);
        $quizAttempt = QuizAttempt::query()->where('organization_id', $organization->getKey())->where('uuid', $attempt)->with('assessment')->firstOrFail();
        $this->authorizeAssignment($membership, $quizAttempt);
        $data = $request->validate(['answers' => ['required', 'array'], 'answers.*.marks_awarded' => ['nullable', 'numeric', 'min:0'], 'answers.*.teacher_feedback' => ['nullable', 'string', 'max:5000'], 'action' => ['required', 'in:draft,approve']]);
        try {
            $overrideReleased = $this->permissions->allows($membership, 'quiz_submissions.override_released');
            if ($data['action'] === 'draft') {
                $this->quizzes->saveDraft($quizAttempt, $this->actor($request), $data['answers'], $overrideReleased);
            } else {
                $this->quizzes->review($quizAttempt, $this->actor($request), $data['answers'], $overrideReleased);
            }
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['review' => $exception->getMessage()]);
        }

        return back()->with('status', $data['action'] === 'draft' ? 'Marking draft saved.' : 'Teacher-reviewed marks approved.');
    }

    public function generatePlan(Request $request, string $attempt): RedirectResponse
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'study_plans.generate'), 403);
        $quizAttempt = QuizAttempt::query()->where('organization_id', $organization->getKey())->where('uuid', $attempt)->with('assessment.subject')->firstOrFail();
        try {
            $this->studyPlans->generate($quizAttempt, $this->actor($request), $request->boolean('regenerate'));
        } catch (DomainException $exception) {
            return back()->withErrors(['study_plan' => $exception->getMessage()]);
        }

        return back()->with('status', $request->boolean('regenerate') ? 'A new study-plan version is ready for review.' : 'A personalized study plan is ready for review.');
    }

    public function approvePlan(Request $request, string $attempt, string $plan): RedirectResponse
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'study_plans.approve'), 403);
        $quizAttempt = QuizAttempt::query()->where('organization_id', $organization->getKey())->where('uuid', $attempt)->firstOrFail();
        $studyPlan = QuizStudyPlan::query()->where('quiz_attempt_id', $quizAttempt->getKey())->where('uuid', $plan)->firstOrFail();
        $request->validate(['summary' => ['nullable', 'string', 'max:2000']]);
        $this->studyPlans->publish($studyPlan, $this->actor($request));

        return back()->with('status', 'Study plan published to the learner and guardian.');
    }

    public function progress(Request $request, string $attempt, string $plan): RedirectResponse
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'quiz_attempts.submit'), 403);
        $learner = $this->learner($request, $organization);
        $quizAttempt = QuizAttempt::query()->where('organization_id', $organization->getKey())->where('learner_profile_id', $learner->getKey())->where('uuid', $attempt)->firstOrFail();
        $studyPlan = QuizStudyPlan::query()->where('quiz_attempt_id', $quizAttempt->getKey())->where('uuid', $plan)->firstOrFail();
        $data = $request->validate(['completed_activity_ids' => ['required', 'array', 'max:100'], 'completed_activity_ids.*' => ['required', 'string', 'max:100'], 'time_spent_minutes' => ['required', 'integer', 'min:1', 'max:1440']]);
        try {
            $this->studyPlans->recordProgress($studyPlan, $learner, $data['completed_activity_ids'], $data['time_spent_minutes']);
        } catch (DomainException $exception) {
            return back()->withErrors(['progress' => $exception->getMessage()]);
        }

        return back()->with('status', 'Study progress updated.');
    }

    public function retest(Request $request, string $attempt, string $plan): RedirectResponse
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'quiz_attempts.submit'), 403);
        $learner = $this->learner($request, $organization);
        $quizAttempt = QuizAttempt::query()->where('organization_id', $organization->getKey())->where('learner_profile_id', $learner->getKey())->where('uuid', $attempt)->firstOrFail();
        $studyPlan = QuizStudyPlan::query()->where('quiz_attempt_id', $quizAttempt->getKey())->where('uuid', $plan)->firstOrFail();
        $data = $request->validate(['responses' => ['required', 'array', 'min:1', 'max:30'], 'responses.*' => ['required', 'string', 'max:5000']]);
        try {
            $this->studyPlans->retest($studyPlan, $learner, $data['responses']);
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['retest' => $exception->getMessage()]);
        }

        return back()->with('status', 'Revision retest evaluated and mastery updated.');
    }

    public function analytics(Request $request): View
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'study_plans.analytics'), 403);

        return view('quizzes.analytics', $this->shared($organization, $membership) + ['analytics' => $this->studyPlans->analytics($organization->getKey())]);
    }

    public function interventions(Request $request): View
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'interventions.view'), 403);

        return view('quizzes.interventions', $this->shared($organization, $membership) + [
            'dashboard' => $this->interventions->dashboard(
                $organization->getKey(),
                $this->actor($request),
                $this->permissions->allows($membership, 'interventions.view_organization'),
            ),
        ]);
    }

    public function release(Request $request, string $attempt): RedirectResponse
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'quiz_submissions.release'), 403);
        $quizAttempt = QuizAttempt::query()->where('organization_id', $organization->getKey())->where('uuid', $attempt)->with('assessment')->firstOrFail();
        $this->authorizeAssignment($membership, $quizAttempt);
        try {
            $this->quizzes->release(
                $quizAttempt,
                $this->actor($request),
                $this->permissions->allows($membership, 'quiz_submissions.override_released'),
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['release' => $exception->getMessage()]);
        }

        return back()->with('status', 'Teacher-approved result released.');
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

    private function authorizeAssignment(Membership $membership, QuizAttempt $quizAttempt): void
    {
        $assessment = $quizAttempt->getRelationValue('assessment');
        abort_unless($this->assignments->actorMayActOn(
            $membership,
            (string) $assessment->getAttribute('class_id'),
            $assessment->getAttribute('subject_id'),
        ), 403);
    }

    private function shared(Organization $organization, Membership $membership): array
    {
        return ['organization' => $organization, 'branding' => $this->organizations->branding($organization), 'permissions' => $this->permissions->permissions($membership)];
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
