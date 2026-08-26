<?php

declare(strict_types=1);

namespace Core\Auth\Application;

use Core\Auth\Domain\Enums\TwoFactorStatus;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Bridges password authentication (AuthService::authenticate()) and
 * final login completion (Auth::login() / AuthService::issueToken())
 * across whichever second-factor step is required — see
 * Core\Auth\Domain\Enums\TwoFactorStatus. The web flow uses this only
 * for status(); it tracks the pending user via the session itself
 * (see App\Http\Controllers\WebAuthController). The API flow is
 * stateless between requests, so it also uses issueChallengeToken() /
 * resolveChallengeToken() — a short-lived encrypted token that proves
 * "this request already presented valid credentials" without yet being
 * a real Sanctum token.
 */
final class TwoFactorLoginService
{
    private const CHALLENGE_TOKEN_TTL_MINUTES = 5;

    public function __construct(
        private readonly TwoFactorEnforcementService $enforcement,
    ) {}

    public function status(User $user): TwoFactorStatus
    {
        if ($user->hasTwoFactorEnabled()) {
            return TwoFactorStatus::ChallengeRequired;
        }
        if ($this->enforcement->isRequiredFor($user)) {
            return TwoFactorStatus::SetupRequired;
        }

        return TwoFactorStatus::NotRequired;
    }

    public function issueChallengeToken(User $user): string
    {
        return Crypt::encryptString(json_encode([
            'user_id' => $user->getKey(),
            'expires_at' => now()->addMinutes(self::CHALLENGE_TOKEN_TTL_MINUTES)->timestamp,
        ], JSON_THROW_ON_ERROR));
    }

    public function resolveChallengeToken(string $token): ?User
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            Log::info('Rejected an unreadable two-factor challenge token.', ['reason' => $exception->getMessage()]);

            return null;
        }

        if (! is_array($payload) || ! isset($payload['user_id'], $payload['expires_at']) || (int) $payload['expires_at'] < now()->timestamp) {
            return null;
        }

        return User::query()->find($payload['user_id']);
    }
}
