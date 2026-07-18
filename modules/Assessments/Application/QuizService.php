<?php

declare(strict_types=1);

namespace Modules\Assessments\Application;

use Core\AIGateway\Application\AIManager;
use Core\AIGateway\Application\DTOs\AIRequest;
use Core\AuditLogs\Application\AuditLogService;
use Core\Notifications\Application\NotificationService;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Assessments\Domain\Enums\AssessmentResultStatus;
use Modules\Assessments\Domain\Enums\AssessmentStatus;
use Modules\Assessments\Domain\Enums\QuestionType;
use Modules\Assessments\Infrastructure\Models\AiGradingRequest;
use Modules\Assessments\Infrastructure\Models\Assessment;
use Modules\Assessments\Infrastructure\Models\AssessmentQuestion;
use Modules\Assessments\Infrastructure\Models\QuizAnswer;
use Modules\Assessments\Infrastructure\Models\QuizAttempt;
use Modules\Learners\Infrastructure\Models\GuardianProfile;
use Modules\Learners\Infrastructure\Models\LearnerProfile;

final class QuizService
{
    private const MAX_AI_GENERATIONS = 2;

    public function __construct(
        private readonly AIManager $ai,
        private readonly AuditLogService $audit,
        private readonly NotificationService $notifications,
        private readonly StudyPlanService $studyPlans,
    ) {}

    public function addQuestion(Assessment $assessment, User $actor, array $data): AssessmentQuestion
    {
        $this->editableBy($assessment, $actor);
        $type = QuestionType::from($data['type']);
        $options = $data['options'] ?? [];
        if ($type->isObjective() && count($options) < 2) {
            throw new DomainException('Objective questions require at least two options.');
        }
        if ($type->isObjective() && collect($options)->where('is_correct', true)->count() !== 1) {
            throw new DomainException('Objective questions require exactly one correct option.');
        }

        return DB::transaction(function () use ($assessment, $data, $type, $options): AssessmentQuestion {
            $order = (int) ($assessment->questions()->max('display_order') ?? 0) + 1;
            $question = $assessment->questions()->create([
                'organization_id' => $assessment->organization_id,
                'type' => $type,
                'prompt' => trim($data['prompt']),
                'marks_available' => $data['marks_available'],
                'display_order' => $order,
                'model_answer' => $data['model_answer'] ?? null,
                'marking_guidance' => $data['marking_guidance'] ?? null,
                'key_concepts' => array_values(array_filter(array_map('trim', $data['key_concepts'] ?? []))),
            ]);
            foreach (array_values($options) as $index => $option) {
                $question->options()->create([
                    'organization_id' => $assessment->organization_id,
                    'label' => trim((string) $option['label']),
                    'is_correct' => (bool) ($option['is_correct'] ?? false),
                    'display_order' => $index + 1,
                ]);
            }
            $assessment->update(['maximum_mark' => $assessment->questions()->sum('marks_available')]);
            $this->audit->record('quizzes.question_added', $assessment, after: ['organization_id' => $assessment->organization_id, 'question_type' => $type->value]);

            return $question->load('options');
        });
    }

    public function publish(Assessment $assessment, User $actor): Assessment
    {
        $this->editableBy($assessment, $actor);
        if (! $assessment->questions()->exists()) {
            throw new DomainException('A quiz cannot be published without questions.');
        }
        $assessment->update(['maximum_mark' => $assessment->questions()->sum('marks_available'), 'status' => AssessmentStatus::Open, 'updated_by' => $actor->getKey()]);
        $this->audit->record('quizzes.published', $assessment, after: ['organization_id' => $assessment->organization_id, 'total_marks' => $assessment->maximum_mark]);

        return $assessment->refresh();
    }

    public function start(Assessment $assessment, LearnerProfile $learner): QuizAttempt
    {
        if ($assessment->status !== AssessmentStatus::Open || ($assessment->opens_at && $assessment->opens_at->isFuture()) || ($assessment->closes_at && $assessment->closes_at->isPast())) {
            throw new DomainException('This quiz is not currently available.');
        }
        $result = $assessment->results()->where('learner_profile_id', $learner->getKey())->first();
        if (! $result || $learner->organization_id !== $assessment->organization_id) {
            throw new DomainException('This quiz is not assigned to the learner.');
        }
        $active = QuizAttempt::query()->where('assessment_id', $assessment->getKey())->where('learner_profile_id', $learner->getKey())->where('status', 'in_progress')->first();
        if ($active) {
            return $active;
        }
        $count = QuizAttempt::query()->where('assessment_id', $assessment->getKey())->where('learner_profile_id', $learner->getKey())->count();
        if ($count >= (int) $assessment->attempt_limit) {
            throw new DomainException('The attempt limit has been reached.');
        }

        return DB::transaction(function () use ($assessment, $learner, $result, $count): QuizAttempt {
            $attempt = QuizAttempt::query()->create([
                'organization_id' => $assessment->organization_id,
                'assessment_id' => $assessment->getKey(),
                'assessment_result_id' => $result->getKey(),
                'learner_profile_id' => $learner->getKey(),
                'attempt_number' => $count + 1,
                'status' => 'in_progress',
                'started_at' => now(),
            ]);
            foreach ($assessment->questions()->get() as $question) {
                $attempt->answers()->create(['organization_id' => $assessment->organization_id, 'assessment_question_id' => $question->getKey(), 'marks_available' => $question->marks_available]);
            }
            $this->audit->record('quiz_attempts.started', $attempt, after: ['organization_id' => $assessment->organization_id]);

            return $attempt->load('answers.question.options');
        });
    }

    public function submit(QuizAttempt $attempt, LearnerProfile $learner, array $answers): QuizAttempt
    {
        if ($attempt->learner_profile_id !== $learner->getKey() || $attempt->status !== 'in_progress') {
            throw new DomainException('This attempt cannot be submitted.');
        }

        return DB::transaction(function () use ($attempt, $answers): QuizAttempt {
            /** @var QuizAttempt $locked */
            $locked = QuizAttempt::query()->whereKey($attempt->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'in_progress') {
                throw new DomainException('This attempt has already been submitted.');
            }
            $stored = $locked->answers()->with('question.options')->lockForUpdate()->get();
            foreach ($stored as $answer) {
                /** @var QuizAnswer $answer */
                $input = $answers[$answer->question->uuid] ?? [];
                if ($answer->question->type->isObjective()) {
                    $option = $answer->question->options->firstWhere('uuid', $input['selected_option_uuid'] ?? null);
                    $answer->update([
                        'selected_option_id' => $option?->getKey(),
                        'marks_awarded' => $option?->is_correct ? $answer->marks_available : 0,
                        'marking_method' => 'automatic',
                        'marked_at' => now(),
                    ]);
                } else {
                    $answer->update(['answer_text' => trim((string) ($input['answer_text'] ?? '')), 'marking_method' => null]);
                }
            }
            $locked->update(['status' => 'submitted', 'submitted_at' => now()]);
            $this->audit->record('quiz_attempts.submitted', $locked, after: ['organization_id' => $locked->organization_id]);

            return $locked->refresh()->load('answers.question.options', 'assessment.subject', 'learner');
        }, 3);
    }

    public function suggestWrittenMark(QuizAnswer $answer, User $teacher, bool $regenerate = false, bool $overrideReleased = false): QuizAnswer
    {
        $answer->loadMissing('question', 'attempt.assessment.subject', 'attempt.learner.currentGrade');
        $this->markableBy($answer->attempt, $teacher, $overrideReleased);
        if ($answer->question->type->isObjective()) {
            throw new DomainException('Objective questions are marked deterministically.');
        }
        if (! in_array($answer->attempt->status, ['submitted', 'marking_draft'], true)) {
            throw new DomainException('Only unapproved submitted answers may be graded.');
        }
        $completed = AiGradingRequest::query()
            ->where('quiz_answer_id', $answer->getKey())
            ->where('request_type', 'written_marking')
            ->where('status', 'completed')
            ->count();
        if ($regenerate && $completed === 0) {
            throw new DomainException('Generate an AI recommendation before regenerating it.');
        }
        if (! $regenerate && $completed > 0) {
            return $answer->refresh();
        }
        if ($completed >= self::MAX_AI_GENERATIONS) {
            throw new DomainException('The AI recommendation may only be regenerated once.');
        }
        $generation = $completed + 1;
        $key = hash('sha256', 'grade:'.$answer->getKey().':'.$generation);
        $request = AiGradingRequest::query()->firstOrCreate(
            ['idempotency_key' => $key],
            ['organization_id' => $answer->organization_id, 'quiz_attempt_id' => $answer->quiz_attempt_id, 'quiz_answer_id' => $answer->getKey(), 'request_type' => 'written_marking', 'status' => 'pending'],
        );
        if ($request->status === 'completed') {
            return $answer->refresh();
        }
        if (! config('hackathon.ai.marking_enabled')) {
            $request->update(['status' => 'failed', 'failure_message' => 'AI marking is disabled.']);
            throw new DomainException('AI marking is unavailable; mark this answer manually.');
        }

        try {
            $response = $this->ai->complete(new AIRequest(
                prompt: json_encode([
                    'subject' => $answer->attempt->assessment->subject?->name,
                    'grade_level' => $answer->attempt->learner->currentGrade?->name,
                    'question' => $answer->question->prompt,
                    'marks_available' => (float) $answer->marks_available,
                    'model_answer' => $answer->question->model_answer,
                    'rubric' => $answer->question->marking_guidance,
                    'key_concepts' => $answer->question->key_concepts ?? [],
                    'learner_answer' => $answer->answer_text,
                ], JSON_THROW_ON_ERROR),
                capability: 'assessment.written_marking',
                tenantId: $answer->organization_id,
                moduleId: 'assessments',
                preferredProvider: config('ai.default_provider'),
                temperature: 0.1,
                maxTokens: (int) config('hackathon.ai.max_output_tokens'),
                metadata: ['instructions' => 'Grade only against the supplied rubric. Give a concise educational rationale, never hidden reasoning.', 'schema_name' => 'quiz_grading', 'json_schema' => $this->gradingSchema()],
            ));
            $result = json_decode($response->content, true, flags: JSON_THROW_ON_ERROR);
            $this->validateGrade($result, (float) $answer->marks_available);
            $usage = $response->usage;
            $input = (int) ($usage['input_tokens'] ?? $usage['prompt_tokens'] ?? 0);
            $output = (int) ($usage['output_tokens'] ?? $usage['completion_tokens'] ?? 0);
            $cost = ($input * config('hackathon.ai.input_cost_per_million') + $output * config('hackathon.ai.output_cost_per_million')) / 1_000_000;
            DB::transaction(function () use ($answer, $request, $response, $result, $input, $output, $cost): void {
                $answer->update(['ai_suggested_mark' => $result['awarded_marks'], 'ai_feedback' => $result, 'marking_method' => 'ai_suggested']);
                $request->update(['provider' => $response->provider, 'model' => $response->model, 'status' => 'completed', 'input_tokens' => $input, 'output_tokens' => $output, 'estimated_cost' => $cost, 'completed_at' => now()]);
            });
        } catch (\Throwable $exception) {
            $request->update(['status' => 'failed', 'failure_message' => 'AI marking unavailable; manual marking is required.']);
            report($exception);
            throw new DomainException('AI marking is unavailable; the submission is safe and can be marked manually.');
        }
        $this->audit->record('quiz_answers.ai_marking_completed', $answer, after: ['organization_id' => $answer->organization_id, 'provider' => $response->provider]);

        return $answer->refresh();
    }

    public function saveDraft(QuizAttempt $attempt, User $teacher, array $marks, bool $overrideReleased = false): QuizAttempt
    {
        $this->markableBy($attempt, $teacher, $overrideReleased);

        return DB::transaction(function () use ($attempt, $teacher, $marks): QuizAttempt {
            $answers = $attempt->answers()->with('question')->lockForUpdate()->get();
            foreach ($answers as $answer) {
                /** @var QuizAnswer $answer */
                if ($answer->question->type->isObjective()) {
                    continue;
                }
                $row = $marks[$answer->uuid] ?? null;
                if (! is_array($row) || ($row['marks_awarded'] ?? null) === null || $row['marks_awarded'] === '') {
                    continue;
                }
                $mark = (float) $row['marks_awarded'];
                if ($mark < 0 || $mark > (float) $answer->marks_available) {
                    throw new DomainException('A final mark must be between zero and the marks available.');
                }
                $adjusted = $answer->ai_suggested_mark !== null && $mark !== (float) $answer->ai_suggested_mark;
                $answer->update(['marks_awarded' => $mark, 'teacher_feedback' => trim((string) ($row['teacher_feedback'] ?? '')), 'marking_method' => $adjusted ? 'teacher_adjusted' : ($answer->ai_suggested_mark === null ? 'teacher_marked' : 'ai_suggested'), 'teacher_adjusted' => $adjusted, 'marked_by' => $teacher->getKey(), 'marked_at' => now()]);
            }
            $attempt->update(['status' => 'marking_draft', 'reviewed_at' => null, 'reviewed_by' => null, 'released_at' => null, 'released_by' => null]);
            $this->audit->record('quiz_results.marking_draft_saved', $attempt, after: ['organization_id' => $attempt->organization_id, 'teacher_id' => $teacher->getKey()]);

            return $attempt->refresh()->load('answers.question');
        }, 3);
    }

    public function review(QuizAttempt $attempt, User $teacher, array $marks, bool $overrideReleased = false): QuizAttempt
    {
        $this->markableBy($attempt, $teacher, $overrideReleased);

        return DB::transaction(function () use ($attempt, $teacher, $marks): QuizAttempt {
            $answers = $attempt->answers()->with('question')->lockForUpdate()->get();
            foreach ($answers as $answer) {
                /** @var QuizAnswer $answer */
                if ($answer->question->type->isObjective()) {
                    continue;
                }
                $row = $marks[$answer->uuid] ?? null;
                if (! is_array($row) || ! is_numeric($row['marks_awarded'] ?? null)) {
                    throw new DomainException('Every written answer requires a final mark.');
                }
                $mark = (float) $row['marks_awarded'];
                $feedback = trim((string) ($row['teacher_feedback'] ?? ''));
                if ($mark < 0 || $mark > (float) $answer->marks_available) {
                    throw new DomainException('A final mark must be between zero and the marks available.');
                }
                if ($feedback === '') {
                    throw new DomainException('Every written answer requires teacher-approved feedback.');
                }
                $adjusted = $answer->ai_suggested_mark !== null && $mark !== (float) $answer->ai_suggested_mark;
                $answer->update(['marks_awarded' => $mark, 'teacher_feedback' => $feedback, 'marking_method' => $adjusted ? 'teacher_adjusted' : ($answer->ai_suggested_mark === null ? 'teacher_marked' : 'ai_accepted'), 'teacher_adjusted' => $adjusted, 'marked_by' => $teacher->getKey(), 'marked_at' => now()]);
            }
            $feedbackRows = [];
            foreach ($answers as $answer) {
                /** @var QuizAnswer $answer */
                if ($answer->marks_awarded === null) {
                    throw new DomainException('Every answer requires a final mark before approval.');
                }
                if (! $answer->question->type->isObjective() && $answer->teacher_feedback !== null && $answer->teacher_feedback !== '') {
                    $feedbackRows[] = $answer->teacher_feedback;
                }
            }
            $score = (float) $answers->sum('marks_awarded');
            $feedback = implode("\n\n", $feedbackRows);
            $attempt->update(['status' => 'reviewed', 'final_score' => $score, 'reviewed_at' => now(), 'reviewed_by' => $teacher->getKey(), 'released_at' => null, 'released_by' => null]);
            $attempt->result()->update(['score' => $score, 'percentage' => round($score / max(1, (float) $attempt->assessment->maximum_mark) * 100, 2), 'result_status' => AssessmentResultStatus::Marked, 'feedback' => $feedback !== '' ? $feedback : 'Marked objectively.', 'marked_by' => $teacher->getKey(), 'marked_at' => now(), 'updated_by' => $teacher->getKey()]);
            $this->audit->record('quiz_results.reviewed', $attempt, after: ['organization_id' => $attempt->organization_id, 'score' => $score, 'teacher_id' => $teacher->getKey(), 'reviewed_at' => $attempt->reviewed_at]);

            return $attempt->refresh()->load('answers.question', 'result');
        }, 3);
    }

    public function release(QuizAttempt $attempt, User $teacher, bool $administrativeOverride = false): QuizAttempt
    {
        if (! $administrativeOverride) {
            $this->ownedBy($attempt->assessment, $teacher);
        }
        if ($attempt->status !== 'reviewed' || $attempt->final_score === null || $attempt->reviewed_by === null) {
            throw new DomainException('Approve complete teacher marking before release.');
        }
        $attempt->loadMissing('assessment', 'learner.user', 'learner.guardianRelationships.guardian.user');
        DB::transaction(function () use ($attempt, $teacher): void {
            $attempt->update(['status' => 'released', 'released_at' => now(), 'released_by' => $teacher->getKey()]);
            $this->audit->record('quiz_results.released', $attempt, after: ['organization_id' => $attempt->organization_id, 'teacher_id' => $teacher->getKey(), 'released_at' => $attempt->released_at]);
        });
        $this->notifyRelease($attempt);
        if (! $attempt->publishedStudyPlan()->exists()) {
            try {
                $this->studyPlans->generate($attempt->refresh(), $teacher, publish: true);
            } catch (DomainException $exception) {
                report($exception);
            }
        }

        return $attempt->refresh();
    }

    private function editableBy(Assessment $assessment, User $actor): void
    {
        if ($assessment->status !== AssessmentStatus::Draft) {
            throw new DomainException('Only draft quizzes may be changed.');
        }
        $this->ownedBy($assessment, $actor);
    }

    private function ownedBy(Assessment $assessment, User $actor): void
    {
        if ($assessment->created_by !== $actor->getKey() && $assessment->staffProfile?->user_id !== $actor->getKey()) {
            throw new DomainException('Only this quiz creator may perform the action.');
        }
    }

    private function markableBy(QuizAttempt $attempt, User $actor, bool $overrideReleased): void
    {
        if ($attempt->status === 'released') {
            if (! $overrideReleased) {
                throw new DomainException('Released results are read-only.');
            }

            return;
        }
        $this->ownedBy($attempt->assessment, $actor);
    }

    private function validateGrade(array $result, float $max): void
    {
        foreach (['awarded_marks', 'max_marks', 'criteria', 'strengths', 'improvements', 'misconceptions', 'grading_rationale', 'confidence', 'requires_teacher_review'] as $key) {
            if (! array_key_exists($key, $result)) {
                throw new DomainException('AI grading output was incomplete.');
            }
        }
        if (! is_numeric($result['awarded_marks']) || (float) $result['awarded_marks'] < 0 || (float) $result['awarded_marks'] > $max || ! is_numeric($result['max_marks']) || (float) $result['max_marks'] !== $max) {
            throw new DomainException('AI grading output contained an invalid mark.');
        }
        if (! is_array($result['criteria']) || $result['criteria'] === []) {
            throw new DomainException('AI grading output did not include rubric criteria.');
        }
        $criteriaTotal = 0.0;
        foreach ($result['criteria'] as $criterion) {
            if (! is_array($criterion) || trim((string) ($criterion['criterion'] ?? '')) === '' || ! is_bool($criterion['met'] ?? null) || ! is_numeric($criterion['marks_awarded'] ?? null) || (float) $criterion['marks_awarded'] < 0) {
                throw new DomainException('AI grading output contained invalid rubric criteria.');
            }
            $criteriaTotal += (float) $criterion['marks_awarded'];
        }
        if (abs($criteriaTotal - (float) $result['awarded_marks']) > 0.001) {
            throw new DomainException('AI rubric criteria did not total the awarded mark.');
        }
        if (! is_numeric($result['confidence']) || (float) $result['confidence'] < 0 || (float) $result['confidence'] > 1 || ! is_bool($result['requires_teacher_review'])) {
            throw new DomainException('AI grading output contained invalid confidence metadata.');
        }
        if (trim((string) $result['grading_rationale']) === '') {
            throw new DomainException('AI grading feedback may not be empty.');
        }
    }

    private function notifyRelease(QuizAttempt $attempt): void
    {
        $data = [
            'message' => 'A quiz result has been released.',
            'assessment' => $attempt->assessment->title,
            'score' => $attempt->final_score,
            'maximum_mark' => $attempt->assessment->maximum_mark,
        ];
        if ($attempt->learner->user) {
            $this->notifications->send($attempt->learner->user, 'assessments.quiz_result_released', $data);
        }
        $guardians = $attempt->learner->guardianRelationships
            ->filter(fn ($relationship) => $relationship->status === 'active'
                && $relationship->deleted_at === null
                && $relationship->receives_academic_communication
                && ($relationship->effective_from === null || $relationship->effective_from->isPast() || $relationship->effective_from->isToday())
                && ($relationship->effective_until === null || $relationship->effective_until->isFuture() || $relationship->effective_until->isToday()))
            ->map(fn ($relationship) => $relationship->guardian)
            ->filter(fn (GuardianProfile $guardian) => $guardian->user !== null && $guardian->status->value === 'active' && $guardian->archived_at === null && $guardian->deleted_at === null)
            ->unique('user_id');
        foreach ($guardians as $guardian) {
            $this->notifications->send($guardian->user, 'assessments.quiz_result_released', $data);
        }
    }

    private function gradingSchema(): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'required' => ['awarded_marks', 'max_marks', 'criteria', 'strengths', 'improvements', 'misconceptions', 'grading_rationale', 'confidence', 'requires_teacher_review'], 'properties' => [
            'awarded_marks' => ['type' => 'number'], 'max_marks' => ['type' => 'number'],
            'criteria' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['criterion', 'met', 'marks_awarded'], 'properties' => ['criterion' => ['type' => 'string'], 'met' => ['type' => 'boolean'], 'marks_awarded' => ['type' => 'number']]]],
            'strengths' => ['type' => 'array', 'items' => ['type' => 'string']], 'improvements' => ['type' => 'array', 'items' => ['type' => 'string']], 'misconceptions' => ['type' => 'array', 'items' => ['type' => 'string']],
            'grading_rationale' => ['type' => 'string'], 'confidence' => ['type' => 'number'], 'requires_teacher_review' => ['type' => 'boolean'],
        ]];
    }
}
