<?php

declare(strict_types=1);

namespace Core\Auth\Application;

use Core\Auth\Application\DTOs\LoginResult;
use Core\Auth\Events\LoginFailed;
use Core\Auth\Events\UserLoggedIn;
use Core\Auth\Events\UserLoggedOut;
use Core\Auth\Exceptions\AccountNotActiveException;
use Core\AuditLogs\Application\AuditLogService;
use Core\Users\Application\UserService;
use Core\Users\Domain\Enums\UserStatus;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrates authentication. Controllers stay thin (see
 * docs/architecture/clean-architecture.md); all login/logout business
 * rules — lockout, status checks, token issuance, auditing — live here
 * so they behave identically regardless of which controller/console
 * command/future mobile flow calls them.
 */
final class AuthService
{
    public function __construct(
        private readonly UserService $users,
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * @throws ValidationException            invalid credentials
     * @throws AccountNotActiveException       valid credentials, inactive account
     */
    public function login(string $email, string $password, string $ipAddress, string $deviceName = 'api'): LoginResult
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            if ($user !== null) {
                $this->users->recordFailedLogin($user);
            }

            event(new LoginFailed($email, $ipAddress));
            $this->auditLog->record(action: 'auth.login_failed', actorEmail: $email);

            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        $this->assertAccountIsUsable($user);

        $this->users->recordSuccessfulLogin($user, $ipAddress);

        $expiresAt = config('sanctum.expiration')
            ? now()->addMinutes((int) config('sanctum.expiration'))
            : null;

        $token = $user->createToken($deviceName, ['*'], $expiresAt);

        event(new UserLoggedIn($user, $ipAddress));
        $this->auditLog->record(action: 'auth.login', target: $user);

        return new LoginResult(user: $user->fresh(), token: $token->plainTextToken);
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();

        event(new UserLoggedOut($user));
        $this->auditLog->record(action: 'auth.logout', target: $user);
    }

    /**
     * Revokes every token for the user — used when an administrator
     * force-locks or suspends an account, so existing sessions don't
     * outlive the status change. See docs/security/policies.md.
     */
    public function revokeAllTokens(User $user): void
    {
        $user->tokens()->delete();
    }

    public function resetPassword(User $user, string $newPassword): void
    {
        $user->update([
            'password' => Hash::make($newPassword),
            'password_changed_at' => now(),
        ]);

        $this->revokeAllTokens($user);

        $this->auditLog->record(action: 'auth.password_reset', target: $user);
    }

    /**
     * @throws AccountNotActiveException
     */
    private function assertAccountIsUsable(User $user): void
    {
        match ($user->status) {
            UserStatus::Locked => throw AccountNotActiveException::locked(),
            UserStatus::Suspended => throw AccountNotActiveException::suspended(),
            UserStatus::Deactivated => throw AccountNotActiveException::deactivated(),
            UserStatus::Active, UserStatus::PendingVerification => null,
        };
    }
}
