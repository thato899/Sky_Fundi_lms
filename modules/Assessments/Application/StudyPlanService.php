<?php

declare(strict_types=1);

namespace Modules\Assessments\Application;

use Core\AIGateway\Application\AIManager;
use Core\AIGateway\Application\DTOs\AIRequest;
use Core\AIGateway\Exceptions\AIGatewayException;
use Core\Analytics\Application\AnalyticsRecorder;
use Core\Analytics\Domain\Enums\AnalyticsMetric;
use Core\AuditLogs\Application\AuditLogService;
use Core\Notifications\Application\NotificationService;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Assessments\Infrastructure\Models\AiGradingRequest;
use Modules\Assessments\Infrastructure\Models\QuizAnswer;
use Modules\Assessments\Infrastructure\Models\QuizAttempt;
use Modules\Assessments\Infrastructure\Models\QuizRevisionAttempt;
use Modules\Assessments\Infrastructure\Models\QuizStudyPlan;
use Modules\Learners\Infrastructure\Models\GuardianProfile;
use Modules\Learners\Infrastructure\Models\LearnerProfile;

final class StudyPlanService
{
    public function __construct(
        private readonly AIManager $ai,
        private readonly AuditLogService $audit,
        private readonly NotificationService $notifications,
        private readonly AnalyticsRecorder $analytics,
    ) {}

    public function generate(QuizAttempt $attempt, User $teacher, bool $regenerate = false, bool $publish = false): QuizStudyPlan
    {
        $attempt->loadMissing('assessment.subject', 'learner.currentGrade', 'answers.question', 'learner.user', 'learner.guardianRelationships.guardian.user');
        $this->ownedBy($attempt, $teacher);
        if (! in_array($attempt->status, ['reviewed', 'released'], true) || $attempt->reviewed_at === null) {
            throw new DomainException('Teacher-approved marking is required before generating a study plan.');
        }
        $latest = QuizStudyPlan::query()->where('quiz_attempt_id', $attempt->getKey())->latest('version')->first();
        if ($latest && ! $regenerate) {
            return $latest;
        }
        if ($regenerate && (! $latest || $latest->status !== 'published')) {
            throw new DomainException('Only a published study plan may be explicitly regenerated.');
        }

        $weaknesses = $this->weaknesses($attempt);
        if ($weaknesses === []) {
            $weaknesses[] = ['concepts' => ['extension practice'], 'question' => 'Consolidation', 'teacher_feedback' => 'Maintain current mastery with spaced revision.', 'misconceptions' => [], 'confidence' => null];
        }
        $version = ($latest?->version ?? 0) + 1;
        $request = AiGradingRequest::query()->firstOrCreate(
            ['idempotency_key' => hash('sha256', 'study-plan:'.$attempt->getKey().':'.$version)],
            ['organization_id' => $attempt->organization_id, 'quiz_attempt_id' => $attempt->getKey(), 'request_type' => 'study_plan', 'status' => 'pending'],
        );

        try {
            $response = $this->ai->complete(new AIRequest(
                prompt: json_encode([
                    'subject' => $attempt->assessment->subject?->name,
                    'grade_level' => $attempt->learner->currentGrade?->name,
                    'teacher_approved_score' => (float) $attempt->final_score,
                    'maximum_mark' => (float) $attempt->assessment->maximum_mark,
                    'weaknesses' => $weaknesses,
                ], JSON_THROW_ON_ERROR),
                capability: 'assessment.adaptive_study_plan',
                tenantId: $attempt->organization_id,
                moduleId: 'assessments',
                preferredProvider: config('ai.default_provider'),
                temperature: 0.2,
                maxTokens: (int) config('hackathon.ai.max_output_tokens'),
                metadata: [
                    'instructions' => 'Create a safe personalized study plan using only supplied weak concepts. Revision exercises must include easy, medium, and challenge difficulty. Recommend descriptive resource titles and search topics, never fabricated URLs. Do not include hidden reasoning.',
                    'schema_name' => 'adaptive_study_plan',
                    'json_schema' => $this->planSchema(),
                ],
            ));
            $content = json_decode($response->content, true, flags: JSON_THROW_ON_ERROR);
            $this->validatePlan($content, $this->weakConcepts($weaknesses));
            [$input, $output, $cost] = $this->usage($response->usage);
            $provider = $response->provider;
            $model = $response->model;
        } catch (AIGatewayException|\JsonException|DomainException $exception) {
            // No provider reachable or unusable output: fall back to the
            // bounded performance-based generator so a released result always
            // ships with a study plan; AI enriches when configured.
            report($exception);
            $content = $this->fallbackPlan($attempt, $weaknesses);
            $this->validatePlan($content, $this->weakConcepts($weaknesses));
            [$input, $output, $cost] = [0, 0, 0.0];
            $provider = 'deterministic_fallback';
            $model = 'performance-rules-v1';
        }

        $plan = DB::transaction(function () use ($attempt, $teacher, $publish, $version, $content, $provider, $model, $request, $input, $output, $cost): QuizStudyPlan {
            if ($publish) {
                QuizStudyPlan::query()->where('quiz_attempt_id', $attempt->getKey())->where('status', 'published')->update(['status' => 'superseded']);
            }
            $plan = QuizStudyPlan::query()->create([
                'organization_id' => $attempt->organization_id,
                'quiz_attempt_id' => $attempt->getKey(),
                'learner_profile_id' => $attempt->learner_profile_id,
                'version' => $version,
                'content' => $content,
                'provider' => $provider,
                'model' => $model,
                'status' => $publish ? 'published' : 'draft',
                'approved_by' => $publish ? $teacher->getKey() : null,
                'approved_at' => $publish ? now() : null,
                'published_by' => $publish ? $teacher->getKey() : null,
                'published_at' => $publish ? now() : null,
                'remaining_concepts' => $content['weak_concepts'],
            ]);
            $request->update(['provider' => $provider, 'model' => $model, 'status' => 'completed', 'input_tokens' => $input, 'output_tokens' => $output, 'estimated_cost' => $cost, 'completed_at' => now()]);

            return $plan;
        });

        $this->audit->record('study_plans.generated', $plan, after: ['organization_id' => $plan->organization_id, 'version' => $plan->version, 'published' => $publish, 'provider' => $provider]);
        $this->analytics->record(AnalyticsMetric::AdaptiveLearning, $plan, metadata: ['event' => 'plan_generated', 'concepts' => $content['weak_concepts'], 'estimated_duration_minutes' => $content['estimated_duration_minutes']]);
        if ($publish) {
            $this->notifyPublished($plan, $attempt, $version > 1);
        }

        return $plan->refresh();
    }

    public function publish(QuizStudyPlan $plan, User $teacher): QuizStudyPlan
    {
        $plan->loadMissing('attempt.assessment', 'attempt.learner.user', 'attempt.learner.guardianRelationships.guardian.user');
        $this->ownedBy($plan->attempt, $teacher);
        if ($plan->status !== 'draft') {
            throw new DomainException('Only a draft study plan may be published.');
        }
        $updated = QuizStudyPlan::query()
            ->where('quiz_attempt_id', $plan->quiz_attempt_id)
            ->where('status', 'published')
            ->whereKeyNot($plan->getKey())
            ->exists();

        DB::transaction(function () use ($plan, $teacher): void {
            QuizStudyPlan::query()->where('quiz_attempt_id', $plan->quiz_attempt_id)->where('status', 'published')->update(['status' => 'superseded']);
            $plan->update(['status' => 'published', 'approved_by' => $teacher->getKey(), 'approved_at' => now(), 'published_by' => $teacher->getKey(), 'published_at' => now()]);
        });
        $this->audit->record('study_plans.published', $plan, after: ['organization_id' => $plan->organization_id, 'version' => $plan->version]);
        $this->notifyPublished($plan, $plan->attempt, $updated);

        return $plan->refresh();
    }

    /**
     * The teacher-authored report to the parent: a motivational note on the
     * learner's performance shown with the released result in the learner
     * view and the guardian portal. Editable on draft and published plans by
     * the owning teacher; every change is audited.
     */
    public function updateTeacherComment(QuizStudyPlan $plan, User $teacher, string $comment): QuizStudyPlan
    {
        $plan->loadMissing('attempt.assessment.staffProfile');
        $this->ownedBy($plan->attempt, $teacher);
        if (! mb_check_encoding($comment, 'UTF-8')) {
            $comment = (string) mb_convert_encoding($comment, 'UTF-8', 'UTF-8');
        }
        $comment = trim($comment);
        if ($comment === '' || mb_strlen($comment) > 2000) {
            throw new DomainException('The report to the parent must be between 1 and 2000 characters.');
        }
        if (! in_array($plan->status, ['draft', 'published'], true)) {
            throw new DomainException('Only a draft or published study plan may carry a parent report.');
        }
        $content = $plan->content;
        $content['teacher_comment'] = $comment;
        $plan->update(['content' => $content]);
        $this->audit->record('study_plans.teacher_comment_updated', $plan, after: ['organization_id' => $plan->organization_id, 'version' => $plan->version]);

        return $plan->refresh();
    }

    public function recordProgress(QuizStudyPlan $plan, LearnerProfile $learner, array $activityIds, int $minutes): QuizStudyPlan
    {
        [$lockedPlan, $wasComplete, $percentage] = DB::transaction(function () use ($plan, $learner, $activityIds, $minutes): array {
            /** @var QuizStudyPlan $lockedPlan */
            $lockedPlan = QuizStudyPlan::query()->whereKey($plan->getKey())->lockForUpdate()->firstOrFail();
            $this->learnerCanUse($lockedPlan, $learner);
            $validIds = collect($lockedPlan->content['daily_schedule'])->pluck('activity_id')
                ->merge(collect($lockedPlan->content['revision_exercises'])->pluck('activity_id'))->unique()->values();
            $completed = collect($lockedPlan->completed_activities ?? [])->merge($activityIds)->unique()->filter(fn ($id) => $validIds->contains($id))->values();
            $percentage = $validIds->isEmpty() ? 100 : (int) round($completed->count() / $validIds->count() * 100);
            $wasComplete = $lockedPlan->completion_percentage === 100;
            $lockedPlan->update([
                'completed_activities' => $completed->all(),
                'completion_percentage' => $percentage,
                'time_spent_minutes' => $lockedPlan->time_spent_minutes + $minutes,
                'last_activity_at' => now(),
                'completed_at' => $percentage === 100 ? ($lockedPlan->completed_at ?? now()) : $lockedPlan->completed_at,
            ]);

            return [$lockedPlan, $wasComplete, $percentage];
        }, 3);
        $this->analytics->record(AnalyticsMetric::AdaptiveLearning, $lockedPlan, value: $percentage, metadata: ['event' => 'progress_updated', 'time_spent_minutes' => $minutes]);
        if (! $wasComplete && $percentage === 100 && $learner->user) {
            $this->notifications->send($learner->user, 'assessments.revision_completed', ['message' => 'Your revision activities are complete.', 'assessment' => $lockedPlan->attempt->assessment->title]);
        }

        return $lockedPlan->refresh();
    }

    public function retest(QuizStudyPlan $plan, LearnerProfile $learner, array $responses): QuizRevisionAttempt
    {
        $revision = DB::transaction(function () use ($plan, $learner, $responses): QuizRevisionAttempt {
            /** @var QuizStudyPlan $lockedPlan */
            $lockedPlan = QuizStudyPlan::query()->whereKey($plan->getKey())->lockForUpdate()->firstOrFail();
            $this->learnerCanUse($lockedPlan, $learner);
            $attemptNumber = ((int) $lockedPlan->revisionAttempts()->max('attempt_number')) + 1;

            return QuizRevisionAttempt::query()->create(['organization_id' => $lockedPlan->organization_id, 'quiz_study_plan_id' => $lockedPlan->getKey(), 'learner_profile_id' => $learner->getKey(), 'attempt_number' => $attemptNumber, 'responses' => $responses, 'status' => 'submitted', 'submitted_at' => now()]);
        }, 3);
        $plan->refresh();
        $questions = collect($plan->content['revision_exercises'])->map(fn ($exercise) => ['activity_id' => $exercise['activity_id'], 'concept' => $exercise['concept'], 'difficulty' => $exercise['difficulty'], 'question' => $exercise['question'], 'success_criteria' => $exercise['success_criteria']])->values()->all();
        $request = AiGradingRequest::query()->firstOrCreate(
            ['idempotency_key' => hash('sha256', 'revision:'.$revision->getKey())],
            ['organization_id' => $plan->organization_id, 'quiz_attempt_id' => $plan->quiz_attempt_id, 'request_type' => 'adaptive_retest', 'status' => 'pending'],
        );

        try {
            $response = $this->ai->complete(new AIRequest(
                prompt: json_encode(['questions' => $questions, 'learner_responses' => $responses], JSON_THROW_ON_ERROR),
                capability: 'assessment.adaptive_retest',
                tenantId: $plan->organization_id,
                moduleId: 'assessments',
                preferredProvider: config('ai.default_provider'),
                temperature: 0.1,
                maxTokens: (int) config('hackathon.ai.max_output_tokens'),
                metadata: ['instructions' => 'Evaluate only against the supplied success criteria. Return concise feedback without hidden reasoning.', 'schema_name' => 'adaptive_retest', 'json_schema' => $this->retestSchema()],
            ));
            $evaluation = json_decode($response->content, true, flags: JSON_THROW_ON_ERROR);
            $this->validateRetest($evaluation, $plan->content['weak_concepts']);
            [$input, $output, $cost] = $this->usage($response->usage);
            DB::transaction(function () use ($revision, $plan, $request, $response, $evaluation, $input, $output, $cost): void {
                $revision->update(['evaluation' => $evaluation, 'score_percentage' => $evaluation['score_percentage'], 'status' => 'evaluated', 'evaluated_at' => now()]);
                $mastered = collect($plan->mastered_concepts ?? [])->merge($evaluation['mastered_concepts'])->unique()->values();
                $remaining = collect($plan->content['weak_concepts'])->diff($mastered)->values();
                $plan->update(['mastered_concepts' => $mastered->all(), 'remaining_concepts' => $remaining->all(), 'last_activity_at' => now()]);
                $request->update(['provider' => $response->provider, 'model' => $response->model, 'status' => 'completed', 'input_tokens' => $input, 'output_tokens' => $output, 'estimated_cost' => $cost, 'completed_at' => now()]);
            });
        } catch (AIGatewayException|\JsonException|DomainException $exception) {
            $request->update(['status' => 'failed', 'failure_message' => 'Adaptive retest evaluation failed.']);
            report($exception);
            throw new DomainException('The revision retest could not be evaluated. Your responses remain saved.', previous: $exception);
        }

        $this->analytics->record(AnalyticsMetric::AdaptiveLearning, $plan, value: (float) $evaluation['score_percentage'], metadata: ['event' => 'retest_evaluated', 'mastered_concepts' => $evaluation['mastered_concepts'], 'remaining_concepts' => $plan->refresh()->remaining_concepts, 'time_to_mastery_minutes' => $plan->time_spent_minutes]);

        return $revision->refresh();
    }

    public function analytics(string $organizationId): array
    {
        $plans = QuizStudyPlan::query()->where('organization_id', $organizationId)->where('status', 'published')->with(['attempt.learner', 'attempt.assessment.classGroup'])->get();
        $concepts = $plans->flatMap(fn (QuizStudyPlan $plan) => $plan->remaining_concepts ?? [])->countBy()->sortDesc();

        return [
            'average_completion' => round((float) $plans->avg('completion_percentage'), 1),
            'most_missed_concepts' => $concepts->take(10),
            'students_needing_intervention' => $plans->filter(fn (QuizStudyPlan $plan) => $plan->completion_percentage < 40 || count($plan->remaining_concepts ?? []) >= 3),
            'class_weaknesses' => $plans->groupBy(fn (QuizStudyPlan $plan) => $plan->attempt->assessment->classGroup?->name ?? 'Unassigned')->map(fn ($group) => $group->flatMap(fn (QuizStudyPlan $plan) => $plan->remaining_concepts ?? [])->countBy()->sortDesc()->take(5)),
        ];
    }

    private function weaknesses(QuizAttempt $attempt): array
    {
        return $attempt->answers->filter(fn (QuizAnswer $answer) => (float) $answer->marks_awarded < (float) $answer->marks_available)->map(fn (QuizAnswer $answer) => [
            'concepts' => $answer->question->key_concepts ?: [$answer->question->prompt],
            'question' => $answer->question->prompt,
            'teacher_feedback' => $answer->teacher_feedback,
            'misconceptions' => $answer->ai_feedback['misconceptions'] ?? [],
            'rubric_failures' => collect($answer->ai_feedback['criteria'] ?? [])->where('met', false)->pluck('criterion')->values()->all(),
            'confidence' => $answer->ai_feedback['confidence'] ?? null,
        ])->values()->all();
    }

    private function weakConcepts(array $weaknesses): array
    {
        return collect($weaknesses)->flatMap(fn ($weakness) => $weakness['concepts'])->map(fn ($concept) => trim((string) $concept))->filter()->unique()->values()->all();
    }

    /**
     * Bounded performance-based plan used when no AI provider is reachable:
     * built strictly from the marked answers' weak concepts with no
     * fabricated resources, so a released result always ships with a plan.
     */
    private function fallbackPlan(QuizAttempt $attempt, array $weaknesses): array
    {
        $concepts = $this->weakConcepts($weaknesses);
        $subject = (string) ($attempt->assessment->subject?->name ?? 'this subject');
        $schedule = [];
        for ($day = 1; $day <= 7; $day++) {
            $concept = $concepts[($day - 1) % count($concepts)];
            $schedule[] = [
                'day' => $day,
                'duration_minutes' => 30,
                'topic' => $concept,
                'activity' => "Review your marked answer on {$concept}, redo it without notes, then write a two-sentence summary of the method.",
                'activity_id' => 'fallback-day-'.$day,
            ];
        }
        $exercises = [];
        foreach ([['easy', 'Define %s in your own words and give one worked example.'], ['medium', 'Solve a new practice problem that uses %s and explain every step.'], ['challenge', 'Create and fully solve your own exam-level question involving %s.']] as $index => [$difficulty, $template]) {
            $concept = $concepts[$index % count($concepts)];
            $exercises[] = ['difficulty' => $difficulty, 'activity_id' => 'fallback-exercise-'.($index + 1), 'concept' => $concept, 'question' => sprintf($template, $concept)];
        }

        return [
            'summary' => "A focused seven-day revision plan for {$subject} built from the marked quiz: it targets ".implode(', ', $concepts).' with daily practice, three graded exercises, and a readiness check.',
            'weak_concepts' => $concepts,
            'learning_goals' => array_map(fn (string $concept): string => "Explain and apply {$concept} without support.", $concepts),
            'daily_schedule' => $schedule,
            'revision_exercises' => $exercises,
            'reflection_questions' => ['Which step of the marked question cost you marks, and why?', 'What would you do differently if you saw a similar question tomorrow?'],
            'recommended_videos' => array_map(fn (string $concept): array => ['title' => ucfirst($concept).' explained step by step', 'search_topic' => $subject.' '.$concept], $concepts),
            'recommended_reading' => array_map(fn (string $concept): array => ['title' => 'Textbook section on '.$concept, 'description' => 'Re-read the worked examples on '.$concept.' and attempt the exercises alongside them.'], $concepts),
            'estimated_duration_minutes' => 210,
            'success_criteria' => array_map(fn (string $concept): string => "Score full marks on a fresh question about {$concept}.", $concepts),
            'next_assessment_recommendation' => 'Take a short retest on '.implode(', ', $concepts).' after completing the seven-day plan.',
            'teacher_comment' => 'This plan was generated from the marked results. Daily, focused practice on the listed concepts will lift the next score — your encouragement at home makes the biggest difference.',
        ];
    }

    private function validatePlan(array $content, array $weakConcepts): void
    {
        foreach (['summary', 'weak_concepts', 'learning_goals', 'daily_schedule', 'revision_exercises', 'reflection_questions', 'recommended_videos', 'recommended_reading', 'estimated_duration_minutes', 'success_criteria', 'next_assessment_recommendation', 'teacher_comment'] as $key) {
            if (! array_key_exists($key, $content)) {
                throw new DomainException('The generated study plan was incomplete.');
            }
        }
        if (trim((string) $content['summary']) === '' || ! is_numeric($content['estimated_duration_minutes']) || (int) $content['estimated_duration_minutes'] < 15 || (int) $content['estimated_duration_minutes'] > 10080) {
            throw new DomainException('The generated study plan contained invalid duration or summary data.');
        }
        $allowed = collect($weakConcepts)->map(fn ($concept) => mb_strtolower($concept));
        foreach ($content['revision_exercises'] as $exercise) {
            if (! is_array($exercise) || ! in_array($exercise['difficulty'] ?? null, ['easy', 'medium', 'challenge'], true) || trim((string) ($exercise['activity_id'] ?? '')) === '' || trim((string) ($exercise['question'] ?? '')) === '' || ! $allowed->contains(mb_strtolower((string) ($exercise['concept'] ?? '')))) {
                throw new DomainException('Revision questions must target supplied weak concepts with valid difficulty.');
            }
        }
        $difficulties = collect($content['revision_exercises'])->pluck('difficulty')->unique();
        foreach (['easy', 'medium', 'challenge'] as $difficulty) {
            if (! $difficulties->contains($difficulty)) {
                throw new DomainException('Revision questions must include easy, medium, and challenge difficulty.');
            }
        }
    }

    private function validateRetest(array $evaluation, array $weakConcepts): void
    {
        foreach (['score_percentage', 'mastered_concepts', 'feedback', 'ready_for_next_assessment'] as $key) {
            if (! array_key_exists($key, $evaluation)) {
                throw new DomainException('The adaptive retest evaluation was incomplete.');
            }
        }
        if (! is_numeric($evaluation['score_percentage']) || (float) $evaluation['score_percentage'] < 0 || (float) $evaluation['score_percentage'] > 100 || trim((string) $evaluation['feedback']) === '' || ! is_bool($evaluation['ready_for_next_assessment'])) {
            throw new DomainException('The adaptive retest evaluation was invalid.');
        }
        if (collect($evaluation['mastered_concepts'])->diff($weakConcepts)->isNotEmpty()) {
            throw new DomainException('The adaptive retest returned an unknown mastered concept.');
        }
    }

    private function learnerCanUse(QuizStudyPlan $plan, LearnerProfile $learner): void
    {
        $plan->loadMissing('attempt.assessment', 'revisionAttempts');
        if ($plan->organization_id !== $learner->organization_id || $plan->learner_profile_id !== $learner->getKey() || $plan->status !== 'published' || $plan->attempt->status !== 'released') {
            throw new DomainException('This published study plan is not available to the learner.');
        }
    }

    private function ownedBy(QuizAttempt $attempt, User $teacher): void
    {
        if ($attempt->assessment->created_by !== $teacher->getKey() && $attempt->assessment->staffProfile?->user_id !== $teacher->getKey()) {
            throw new DomainException('Only this quiz creator may manage its study plans.');
        }
    }

    private function usage(array $usage): array
    {
        $input = (int) ($usage['input_tokens'] ?? $usage['prompt_tokens'] ?? 0);
        $output = (int) ($usage['output_tokens'] ?? $usage['completion_tokens'] ?? 0);
        $cost = ($input * config('hackathon.ai.input_cost_per_million') + $output * config('hackathon.ai.output_cost_per_million')) / 1_000_000;

        return [$input, $output, $cost];
    }

    private function notifyPublished(QuizStudyPlan $plan, QuizAttempt $attempt, bool $updated): void
    {
        $data = ['message' => $updated ? 'An updated study plan is available.' : 'Your personalized study plan is available.', 'assessment' => $attempt->assessment->title, 'version' => $plan->version];
        if ($attempt->learner->user) {
            $this->notifications->send($attempt->learner->user, $updated ? 'assessments.study_plan_updated' : 'assessments.study_plan_available', $data);
        }
        $guardians = $attempt->learner->guardianRelationships
            ->filter(fn ($relationship) => $relationship->status === 'active' && $relationship->deleted_at === null && $relationship->receives_academic_communication)
            ->map(fn ($relationship) => $relationship->guardian)
            ->filter(fn (GuardianProfile $guardian) => $guardian->user !== null && $guardian->status->value === 'active' && $guardian->archived_at === null && $guardian->deleted_at === null)
            ->unique('user_id');
        foreach ($guardians as $guardian) {
            $this->notifications->send($guardian->user, 'assessments.study_plan_published', $data);
        }
    }

    private function planSchema(): array
    {
        $strings = ['type' => 'array', 'items' => ['type' => 'string']];

        return ['type' => 'object', 'additionalProperties' => false, 'required' => ['summary', 'weak_concepts', 'learning_goals', 'daily_schedule', 'revision_exercises', 'reflection_questions', 'recommended_videos', 'recommended_reading', 'estimated_duration_minutes', 'success_criteria', 'next_assessment_recommendation', 'teacher_comment'], 'properties' => [
            'summary' => ['type' => 'string'], 'weak_concepts' => $strings, 'learning_goals' => $strings,
            'daily_schedule' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['activity_id', 'day', 'duration_minutes', 'topic', 'activity'], 'properties' => ['activity_id' => ['type' => 'string'], 'day' => ['type' => 'integer'], 'duration_minutes' => ['type' => 'integer'], 'topic' => ['type' => 'string'], 'activity' => ['type' => 'string']]]],
            'revision_exercises' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['activity_id', 'concept', 'difficulty', 'question', 'success_criteria'], 'properties' => ['activity_id' => ['type' => 'string'], 'concept' => ['type' => 'string'], 'difficulty' => ['type' => 'string', 'enum' => ['easy', 'medium', 'challenge']], 'question' => ['type' => 'string'], 'success_criteria' => ['type' => 'string']]]],
            'reflection_questions' => $strings,
            'recommended_videos' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['title', 'search_topic'], 'properties' => ['title' => ['type' => 'string'], 'search_topic' => ['type' => 'string']]]],
            'recommended_reading' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['title', 'description'], 'properties' => ['title' => ['type' => 'string'], 'description' => ['type' => 'string']]]],
            'estimated_duration_minutes' => ['type' => 'integer'], 'success_criteria' => $strings, 'next_assessment_recommendation' => ['type' => 'string'], 'teacher_comment' => ['type' => 'string'],
        ]];
    }

    private function retestSchema(): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'required' => ['score_percentage', 'mastered_concepts', 'feedback', 'ready_for_next_assessment'], 'properties' => [
            'score_percentage' => ['type' => 'number'],
            'mastered_concepts' => ['type' => 'array', 'items' => ['type' => 'string']],
            'feedback' => ['type' => 'string'],
            'ready_for_next_assessment' => ['type' => 'boolean'],
        ]];
    }
}
