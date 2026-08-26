<?php

declare(strict_types=1);

namespace Core\Subscriptions\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Subscriptions\Http\Resources\PlanResource;
use Core\Subscriptions\Infrastructure\Models\Plan;
use Illuminate\Http\JsonResponse;

final class PlanController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->ok(PlanResource::collection(Plan::query()->where('is_active', true)->orderBy('price')->get()));
    }
}
