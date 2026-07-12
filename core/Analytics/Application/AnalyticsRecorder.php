<?php

declare(strict_types=1);

namespace Core\Analytics\Application;

use Core\Analytics\Domain\Enums\AnalyticsMetric;
use Core\Analytics\Infrastructure\Models\AnalyticsEvent;
use Illuminate\Database\Eloquent\Model;

/**
 * The single write path for platform analytics. Every Core service
 * and future module records through this rather than writing to
 * analytics_events directly — see core/Analytics/README.md.
 * Deliberately synchronous and cheap (a single insert); if volume ever
 * demands it, queueing the write is an internal change here, not a
 * change to every call site.
 */
final class AnalyticsRecorder
{
    public function record(AnalyticsMetric $metric, ?Model $subject = null, float $value = 1.0, array $metadata = []): void
    {
        AnalyticsEvent::create([
            'metric' => $metric->value,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'value' => $value,
            'metadata' => $metadata,
            'recorded_at' => now(),
        ]);
    }

    /**
     * A simple count-by-day summary for a metric over a date range —
     * infrastructure a future dashboard queries, not a dashboard
     * itself, per the brief ("No dashboards yet").
     */
    public function summarize(AnalyticsMetric $metric, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return AnalyticsEvent::query()
            ->where('metric', $metric->value)
            ->whereBetween('recorded_at', [$from, $to])
            ->selectRaw('date(recorded_at) as day, sum(value) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->day => (float) $row->total])
            ->all();
    }
}
