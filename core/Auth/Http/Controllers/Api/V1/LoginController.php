<?php

declare(strict_types=1);

namespace Core\Auth\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Auth\Application\AuthService;
use Core\Auth\Application\TwoFactorLoginService;
use Core\Auth\Domain\Enums\TwoFactorStatus;
use Core\Auth\Http\Requests\LoginRequest;
use Core\Users\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

final class LoginController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AuthService $auth,
        private readonly TwoFactorLoginService $twoFactorLogin,
    ) {}

    public function store(LoginRequest $request): JsonResponse
    {
        $user = $this->auth->authenticate(
            email: $request->string('email')->value(),
            password: $request->string('password')->value(),
            ipAddress: $request->ip() ?? '0.0.0.0',
        );

        $status = $this->twoFactorLogin->status($user);
        if ($status !== TwoFactorStatus::NotRequired) {
            return $this->ok([
                'two_factor' => $status->value,
                'two_factor_token' => $this->twoFactorLogin->issueChallengeToken($user),
            ]);
        }

        $result = $this->auth->issueToken($user, $request->string('device_name', 'api')->value());

        return $this->ok([
            'user' => new UserResource($result->user),
            'token' => $result->token,
            'token_type' => $result->tokenType,
        ]);
    }
}
