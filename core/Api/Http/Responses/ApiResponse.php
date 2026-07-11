<?php

declare(strict_types=1);

namespace Core\Api\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Standard success-response shaping, per docs/api/conventions.md.
 * Controllers use this trait rather than building JsonResponse arrays
 * by hand, so every endpoint's success shape stays consistent.
 */
trait ApiResponse
{
    protected function ok(JsonResource|ResourceCollection|array $data, int $status = 200): JsonResponse
    {
        if ($data instanceof JsonResource || $data instanceof ResourceCollection) {
            return $data->response()->setStatusCode($status);
        }

        return response()->json(['data' => $data], $status);
    }

    protected function created(JsonResource|array $data): JsonResponse
    {
        return $this->ok($data, 201);
    }

    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    protected function message(string $message, int $status = 200): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
