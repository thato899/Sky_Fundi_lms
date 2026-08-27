<?php

declare(strict_types=1);

namespace Core\Auth\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Auth\Application\AuthService;
use Core\Auth\Application\TwoFactorAuthenticationService;
use Core\Auth\Application\TwoFactorLoginService;
use Core\Auth\Http\Requests\TwoFactorChallengeRequest;
use Core\Users\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

/**
 * Second step of a two-factor-gated API login: exchanges the short-lived
 * challenge token from LoginController plus a TOTP or recovery code for
 * a real Sanctum token. See TwoFactorLoginService for the token format.
 */
final class TwoFactorChallengeController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AuthService $auth,
        private readonly TwoFactorAuthenticationService $twoFactor,
        private readonly TwoFactorLoginService $twoFactorLogin,
    ) {}

    public function store(TwoFactorChallengeRequest $request): JsonResponse
    {
        $user = $this->twoFactorLogin->resolveChallengeToken($request->string('two_factor_token')->value());
        if ($user === null) {
            return $this->message('This two-factor challenge has expired. Please log in again.', 422);
        }

        $code = $request->string('code')->value();
        $recoveryCode = $request->string('recovery_code')->value();
        $verified = ($code !== '' && $this->twoFactor->verify($user, $code))
            || ($recoveryCode !== '' && $this->twoFactor->verifyRecoveryCode($user, $recoveryCode));

        if (! $verified) {
            return $this->message('The provided code is invalid.', 422);
        }

        $result = $this->auth->issueToken($user, $request->string('device_name', 'api')->value());

        return $this->ok([
            'user' => new UserResource($result->user),
            'token' => $result->token,
            'token_type' => $result->tokenType,
        ]);
    }
}
