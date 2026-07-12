<?php

declare(strict_types=1);

namespace Core\Analytics\Http\Controllers\Api\V1;

use Core\Analytics\Application\AnalyticsRecorder;
use Core\Analytics\Domain\Enums\AnalyticsMetric;
use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AnalyticsController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AnalyticsRecorder $analytics,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        $metric = AnalyticsMetric::from($request->string('metric')->value());
        $from = $request->date('from') ?? now()->subDays(30);
        $to = $request->date('to') ?? now();

        return $this->ok(['data' => $this->analytics->summarize($metric, $from, $to)]);
    }

    public function metrics(): JsonResponse
    {
        return $this->ok(['data' => array_map(fn ($case) => $case->value, AnalyticsMetric::cases())]);
    }
}
