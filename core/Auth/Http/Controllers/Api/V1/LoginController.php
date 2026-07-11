<?php

declare(strict_types=1);

namespace Core\Auth\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Auth\Application\AuthService;
use Core\Auth\Http\Requests\LoginRequest;
use Core\Users\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

final class LoginController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AuthService $auth,
    ) {}

    public function store(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login(
            email: $request->string('email')->value(),
            password: $request->string('password')->value(),
            ipAddress: $request->ip() ?? '0.0.0.0',
            deviceName: $request->string('device_name', 'api')->value(),
        );

        return $this->ok([
            'user' => new UserResource($result->user),
            'token' => $result->token,
            'token_type' => $result->tokenType,
        ]);
    }
}
