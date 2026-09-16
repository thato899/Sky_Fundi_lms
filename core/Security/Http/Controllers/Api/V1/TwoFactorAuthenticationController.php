<?php

declare(strict_types=1);

namespace Core\Security\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Security\Application\TwoFactorAuthenticationService;
use Core\Security\Infrastructure\Models\TwoFactorAuthentication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class TwoFactorAuthenticationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly TwoFactorAuthenticationService $twoFactor,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return $this->ok(['enabled' => $this->twoFactor->enabled($request->user())]);
    }

    public function begin(Request $request): JsonResponse
    {
        $secret = $this->twoFactor->begin($request->user());

        return $this->ok([
            'secret' => $secret,
            'otpauth_uri' => $this->otpauthUri($request->user()->email, $secret),
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $code = $this->validatedCode($request);

        try {
            $result = $this->twoFactor->confirm($request->user(), $code);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['code' => [$exception->getMessage()]]);
        }

        return $this->ok($result);
    }

    public function destroy(Request $request): JsonResponse
    {
        $code = $this->validatedCode($request);

        if (! $this->twoFactor->verify($request->user(), $code)) {
            throw ValidationException::withMessages(['code' => ['The authentication code is invalid.']]);
        }

        TwoFactorAuthentication::query()->whereKey($request->user()->id)->delete();

        return $this->message('Two-factor authentication disabled.');
    }

    private function validatedCode(Request $request): string
    {
        return (string) $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ])['code'];
    }

    private function otpauthUri(string $email, string $secret): string
    {
        $issuer = rawurlencode((string) config('app.name', 'Sky Fundi'));

        return "otpauth://totp/{$issuer}:".rawurlencode($email)."?secret={$secret}&issuer={$issuer}";
    }
}
