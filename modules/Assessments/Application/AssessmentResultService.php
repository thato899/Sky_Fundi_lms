<?php

declare(strict_types=1);

namespace Modules\Assessments\Application;

use Core\AuditLogs\Application\AuditLogService;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Assessments\Domain\Enums\AssessmentResultStatus;
use Modules\Assessments\Domain\Enums\AssessmentStatus;
use Modules\Assessments\Infrastructure\Models\Assessment;
use Modules\Assessments\Infrastructure\Models\AssessmentResult;

final class AssessmentResultService
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function record(Assessment $assessment, User $actor, array $rows): Assessment
    {
        return DB::transaction(function () use ($assessment, $actor, $rows): Assessment {
            /** @var Assessment $locked */
            $locked = Assessment::query()->whereKey($assessment->getKey())->lockForUpdate()->firstOrFail();
            if (! in_array($locked->getAttribute('status'), [AssessmentStatus::Draft, AssessmentStatus::Open], true)) {
                throw new DomainException('Finalized or cancelled marks cannot be changed.');
            }
            /** @var Collection<string, AssessmentResult> $results */
            $results = $locked->results()->with('learner')->lockForUpdate()->get()->keyBy('uuid');
            if (count($rows) !== $results->count() || count(array_unique(array_column($rows, 'result_uuid'))) !== count($rows)) {
                throw new DomainException('The complete eligible mark sheet must be submitted atomically.');
            }
            $updates = [];
            foreach ($rows as $row) {
                $result = $results->get($row['result_uuid'] ?? '');
                if (! $result instanceof AssessmentResult || $result->getAttribute('organization_id') !== $locked->getAttribute('organization_id') || $result->getRelation('learner')->getAttribute('current_class_id') !== $locked->getAttribute('class_id')) {
                    throw new DomainException('The mark sheet contains an invalid or ineligible learner.');
                }
                $status = AssessmentResultStatus::tryFrom((string) ($row['result_status'] ?? ''));
                if ($status === null) {
                    throw new DomainException('The mark sheet contains an invalid result status.');
                }
                $score = $row['score'] ?? null;
                if ($status === AssessmentResultStatus::Marked) {
                    if ($score === null || $score === '' || ! is_numeric($score) || (float) $score < 0 || (float) $score > (float) $locked->getAttribute('maximum_mark')) {
                        throw new DomainException('Every marked score must be numeric, non-negative, and no greater than the maximum mark.');
                    }
                    $score = round((float) $score, 2);
                    $percentage = round(($score / (float) $locked->getAttribute('maximum_mark')) * 100, 2);
                } else {
                    $score = null;
                    $percentage = null;
                }
                $updates[] = [$result, ['result_status' => $status, 'score' => $score, 'percentage' => $percentage, 'feedback' => $row['feedback'] ?? null, 'private_note' => $row['private_note'] ?? null, 'marked_by' => $status === AssessmentResultStatus::Marked ? $actor->getKey() : null, 'marked_at' => $status === AssessmentResultStatus::Marked ? now() : null, 'updated_by' => $actor->getKey()]];
            }
            foreach ($updates as [$result, $data]) {
                $result->update($data);
            }
            $locked->update(['status' => AssessmentStatus::Open, 'updated_by' => $actor->getKey()]);
            $this->audit->record('assessment.mark_sheet_recorded', $locked, after: ['organization_id' => $locked->getAttribute('organization_id'), 'result_count' => count($updates), 'marked_count' => collect($updates)->filter(fn ($x) => $x[1]['result_status'] === AssessmentResultStatus::Marked)->count()]);

            return $locked->refresh()->load('results.learner');
        }, 3);
    }
}
