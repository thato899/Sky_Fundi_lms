<?php

declare(strict_types=1);

namespace Modules\Assessments\Application;

use Core\AIGateway\Application\AIManager;
use Core\AIGateway\Application\DTOs\AIRequest;
use Core\Analytics\Application\AnalyticsRecorder;
use Core\Analytics\Domain\Enums\AnalyticsMetric;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Assessments\Infrastructure\Models\QuizAttempt;
use Modules\Assessments\Infrastructure\Models\QuizStudyPlan;

final class InterventionDashboardService
{
    public function __construct(
        private readonly AIManager $ai,
        private readonly AnalyticsRecorder $analytics,
    ) {}

    /** @return array<string, mixed> */
    public function dashboard(string $organizationId, User $teacher, bool $organizationWide = false): array
    {
        $attempts = $this->query($organizationId, $teacher, $organizationWide)->get();
        $learners = $attempts->map(fn (QuizAttempt $attempt): array => $this->learnerRow($attempt));
        $concepts = $this->concepts($attempts);
        $queue = $learners->filter(fn (array $row): bool => in_array($row['risk_level'], ['red', 'orange'], true))
            ->sortBy([['risk_score', 'desc'], ['last_activity_at', 'asc']])->values();

        return [
            'overview' => [
                'learners' => $learners->pluck('learner_id')->unique()->count(),
                'average_class_mark' => round((float) $learners->avg('score_percentage'), 1),
                'average_completion' => round((float) $learners->avg('completion_percentage'), 1),
                'study_streak_average' => round((float) $learners->avg('study_streak'), 1),
                'revision_completion' => round((float) $learners->avg('revision_completion'), 1),
                'learners_at_risk' => $queue->pluck('learner_id')->unique()->count(),
                'ready_for_reassessment' => $learners->where('ready_for_reassessment', true)->count(),
            ],
            'weak_concepts' => $concepts,
            'learners' => $learners->values(),
            'intervention_queue' => $queue,
            'mastery' => $this->mastery($attempts),
            'trends' => $this->trends($attempts),
        ];
    }

    /** @return array<string, mixed> */
    public function recommendations(string $organizationId, User $teacher, bool $organizationWide = false): array
    {
        $dashboard = $this->dashboard($organizationId, $teacher, $organizationWide);
        $response = $this->ai->complete(new AIRequest(
            prompt: json_encode([
                'weak_concepts' => collect($dashboard['weak_concepts'])->take(10)->values(),
                'high_risk_queue' => collect($dashboard['intervention_queue'])->take(20)->map->only([
                    'subject', 'weak_concept', 'risk_level', 'score_percentage', 'completion_percentage',
                ])->values(),
            ], JSON_THROW_ON_ERROR),
            capability: 'assessment.teacher_interventions',
            tenantId: $organizationId,
            moduleId: 'assessments',
            preferredProvider: config('ai.default_provider'),
            temperature: 0.1,
            maxTokens: 1200,
            metadata: [
                'instructions' => 'Return concise, practical teacher interventions based only on supplied aggregates. Never include hidden reasoning.',
                'schema_name' => 'teacher_interventions',
                'json_schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['suggestions'],
                    'properties' => ['suggestions' => [
                        'type' => 'array', 'minItems' => 1, 'maxItems' => 10,
                        'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['concept', 'action', 'estimated_minutes'], 'properties' => [
                            'concept' => ['type' => 'string'],
                            'action' => ['type' => 'string'],
                            'estimated_minutes' => ['type' => 'integer', 'minimum' => 5, 'maximum' => 120],
                        ]],
                    ]],
                ],
            ],
        ));
        $content = json_decode($response->content, true, flags: JSON_THROW_ON_ERROR);
        $this->analytics->record(AnalyticsMetric::AdaptiveLearning, $teacher, metadata: ['event' => 'intervention_recommendations_generated', 'suggestion_count' => count($content['suggestions'] ?? [])]);

        return $content;
    }

    /** @return Builder<QuizAttempt> */
    private function query(string $organizationId, User $teacher, bool $organizationWide): Builder
    {
        return QuizAttempt::query()
            ->where('organization_id', $organizationId)
            ->where('status', 'released')
            ->when(! $organizationWide, fn (Builder $query) => $query->whereHas('assessment', fn (Builder $assessment) => $assessment
                ->where('created_by', $teacher->getKey())
                ->orWhereHas('staffProfile', fn (Builder $staff) => $staff->where('user_id', $teacher->getKey()))))
            ->with(['learner', 'assessment.subject', 'answers.question', 'publishedStudyPlan.revisionAttempts']);
    }

    /** @return array<string, mixed> */
    private function learnerRow(QuizAttempt $attempt): array
    {
        $plan = $attempt->publishedStudyPlan;
        $maximum = max(1.0, (float) $attempt->assessment->maximum_mark);
        $score = round((float) $attempt->final_score / $maximum * 100, 1);
        $completion = $plan?->completion_percentage ?? 0;
        $remaining = count($plan?->remaining_concepts ?? []);
        $adjustments = $attempt->answers->where('teacher_adjusted', true)->count();
        $revision = $plan?->revisionAttempts->where('status', 'evaluated')->sortByDesc('attempt_number')->first();
        $inactive = $plan?->last_activity_at === null || $plan->last_activity_at->lt(now()->subDays((int) config('hackathon.adaptive.inactive_days', 7)));
        $risk = ($score < 40 ? 4 : ($score < 60 ? 2 : ($score < 75 ? 1 : 0)))
            + ($completion === 0 ? 3 : ($completion < 40 ? 2 : ($completion < 75 ? 1 : 0)))
            + ($remaining >= 3 ? 2 : ($remaining > 0 ? 1 : 0))
            + ($revision === null ? 1 : 0) + ($inactive ? 1 : 0) + ($adjustments > 0 ? 1 : 0);
        $level = $risk >= 8 ? 'red' : ($risk >= 5 ? 'orange' : ($risk >= 3 ? 'yellow' : 'green'));
        $weak = $plan?->remaining_concepts[0] ?? $attempt->answers->first(fn ($answer) => (float) $answer->marks_awarded < (float) $answer->marks_available)?->question?->key_concepts[0] ?? 'General revision';

        return [
            'learner_id' => $attempt->learner_profile_id,
            'learner' => trim($attempt->learner->first_name.' '.$attempt->learner->last_name),
            'subject' => $attempt->assessment->subject?->name ?? 'Unassigned',
            'weak_concept' => $weak,
            'score_percentage' => $score,
            'completion_percentage' => $completion,
            'remaining_concepts' => $remaining,
            'revision_completion' => $revision?->score_percentage ?? 0,
            'study_streak' => $this->studyStreak($plan),
            'teacher_adjustments' => $adjustments,
            'risk_score' => $risk,
            'risk_level' => $level,
            'recommended_action' => $this->defaultAction($level, $weak),
            'last_activity_at' => $plan?->last_activity_at?->toIso8601String(),
            'estimated_intervention_minutes' => $level === 'red' ? 30 : ($level === 'orange' ? 20 : 10),
            'ready_for_reassessment' => $completion >= 80 && $remaining === 0,
        ];
    }

    private function studyStreak(?QuizStudyPlan $plan): int
    {
        if (! $plan?->last_activity_at || $plan->last_activity_at->lt(now()->subDay())) {
            return 0;
        }

        return min(7, max(1, (int) ceil($plan->time_spent_minutes / 30)));
    }

    private function defaultAction(string $level, string $concept): string
    {
        return match ($level) {
            'red' => "Meet today for guided practice on {$concept}.",
            'orange' => "Assign a scaffolded revision activity on {$concept}.",
            'yellow' => "Check understanding of {$concept} at the next lesson.",
            default => "Continue independent practice on {$concept}.",
        };
    }

    /** @return Collection<int, array<string, mixed>> */
    private function concepts(Collection $attempts): Collection
    {
        return $attempts->flatMap(function (QuizAttempt $attempt): array {
            return $attempt->answers->filter(fn ($answer) => (float) $answer->marks_awarded < (float) $answer->marks_available)
                ->flatMap(fn ($answer) => collect($answer->question->key_concepts ?: ['General'])->map(fn ($concept) => [
                    'concept' => $concept,
                    'learner_id' => $attempt->learner_profile_id,
                    'score' => (float) $answer->marks_available > 0 ? (float) $answer->marks_awarded / (float) $answer->marks_available * 100 : 0,
                    'confidence' => (float) ($answer->ai_feedback['confidence'] ?? 0),
                ]))->all();
        })->groupBy('concept')->map(fn (Collection $rows, string $concept) => [
            'concept' => $concept,
            'affected_learners' => $rows->pluck('learner_id')->unique()->count(),
            'average_score' => round((float) $rows->avg('score'), 1),
            'average_confidence' => round((float) $rows->avg('confidence'), 2),
            'recommended_intervention' => "Teach {$concept} with a worked example, then check independently.",
        ])->sortByDesc('affected_learners')->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function mastery(Collection $attempts): Collection
    {
        return $attempts->flatMap(fn (QuizAttempt $attempt) => collect($attempt->publishedStudyPlan?->content['weak_concepts'] ?? [])->map(fn ($concept) => [
            'learner' => trim($attempt->learner->first_name.' '.$attempt->learner->last_name),
            'concept' => $concept,
            'mastery_percentage' => in_array($concept, $attempt->publishedStudyPlan?->mastered_concepts ?? [], true) ? 100 : (int) ($attempt->publishedStudyPlan?->completion_percentage ?? 0),
        ]))->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function trends(Collection $attempts): Collection
    {
        return $attempts->groupBy(fn (QuizAttempt $attempt) => $attempt->released_at?->format('Y-m-d') ?? 'unknown')->map(fn (Collection $rows, string $date) => [
            'date' => $date,
            'average_score' => round((float) $rows->avg(fn ($attempt) => (float) $attempt->final_score / max(1, (float) $attempt->assessment->maximum_mark) * 100), 1),
            'study_completion' => round((float) $rows->avg(fn ($attempt) => $attempt->publishedStudyPlan?->completion_percentage ?? 0), 1),
            'ai_usage' => $rows->sum(fn ($attempt) => $attempt->answers->whereNotNull('ai_suggested_mark')->count()),
            'teacher_overrides' => $rows->sum(fn ($attempt) => $attempt->answers->where('teacher_adjusted', true)->count()),
            'intervention_success' => $rows->filter(fn ($attempt) => ($attempt->publishedStudyPlan?->revisionAttempts->max('score_percentage') ?? 0) >= 70)->count(),
            'time_to_mastery_minutes' => round((float) $rows->avg(fn ($attempt) => $attempt->publishedStudyPlan?->time_spent_minutes ?? 0), 1),
        ])->values();
    }
}
