<?php

declare(strict_types=1);

namespace Core\Subscriptions\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Subscriptions\Application\SubscriptionService;
use Core\Subscriptions\Http\Requests\RecordUsageRequest;
use Core\Subscriptions\Http\Requests\StoreSubscriptionRequest;
use Core\Subscriptions\Http\Resources\SubscriptionResource;
use Core\Subscriptions\Infrastructure\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SubscriptionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $subscriptions = Subscription::query()
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')->value()))
            ->latest()
            ->paginate((int) $request->integer('per_page', 25));

        return $this->ok(SubscriptionResource::collection($subscriptions));
    }

    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        return $this->created(new SubscriptionResource($this->subscriptions->start($request->validated())));
    }

    public function show(Subscription $subscription): JsonResponse
    {
        return $this->ok(new SubscriptionResource($subscription));
    }

    public function recordUsage(RecordUsageRequest $request, Subscription $subscription): JsonResponse
    {
        return $this->ok(new SubscriptionResource($this->subscriptions->recordUsage($subscription, $request->validated())));
    }

    public function suspend(Subscription $subscription): JsonResponse
    {
        return $this->ok(new SubscriptionResource($this->subscriptions->suspend($subscription)));
    }

    public function reactivate(Subscription $subscription): JsonResponse
    {
        return $this->ok(new SubscriptionResource($this->subscriptions->reactivate($subscription)));
    }

    public function cancel(Subscription $subscription): JsonResponse
    {
        return $this->ok(new SubscriptionResource($this->subscriptions->cancel($subscription)));
    }

    public function history(Subscription $subscription): JsonResponse
    {
        return $this->ok(['data' => $this->subscriptions->history($subscription)]);
    }
}
