<?php

declare(strict_types=1);

namespace Core\Users\Application;

use Core\AuditLogs\Application\AuditLogService;
use Core\Users\Application\DTOs\CreateUserData;
use Core\Users\Domain\Enums\UserStatus;
use Core\Users\Events\UserCreated;
use Core\Users\Events\UserLocked;
use Core\Users\Events\UserSuspended;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Orchestrates user lifecycle operations. Controllers call this service;
 * they never touch the User model's persistence directly, per
 * docs/architecture/clean-architecture.md#application--service-layer.
 */
final class UserService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function create(CreateUserData $data): User
    {
        $user = User::create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => Hash::make($data->password),
            'status' => UserStatus::PendingVerification,
            'timezone' => $data->timezone,
            'locale' => $data->locale,
            'password_changed_at' => now(),
        ]);

        event(new UserCreated($user));

        $this->auditLog->record(
            action: 'user.created',
            target: $user,
            after: ['email' => $user->email, 'status' => $user->status->value],
        );

        return $user;
    }

    public function suspend(User $user, ?string $reason = null): User
    {
        $before = ['status' => $user->status->value];

        $user->update(['status' => UserStatus::Suspended]);

        event(new UserSuspended($user));

        $this->auditLog->record(
            action: 'user.suspended',
            target: $user,
            before: $before,
            after: ['status' => $user->status->value, 'reason' => $reason],
        );

        return $user;
    }

    public function reactivate(User $user): User
    {
        $before = ['status' => $user->status->value];

        $user->update([
            'status' => UserStatus::Active,
            'failed_login_attempts' => 0,
            'locked_at' => null,
        ]);

        $this->auditLog->record(
            action: 'user.reactivated',
            target: $user,
            before: $before,
            after: ['status' => $user->status->value],
        );

        return $user;
    }

    /**
     * Called by Core\Auth\Application\AuthService on every failed login
     * attempt. Locks the account once the configured threshold is hit —
     * see docs/security/policies.md and AUTH_MAX_LOGIN_ATTEMPTS.
     */
    public function recordFailedLogin(User $user): User
    {
        $attempts = $user->failed_login_attempts + 1;
        $maxAttempts = (int) config('services.auth.max_login_attempts', 5);

        $user->failed_login_attempts = $attempts;

        if ($attempts >= $maxAttempts) {
            $user->status = UserStatus::Locked;
            $user->locked_at = now();
        }

        $user->save();

        if ($user->status === UserStatus::Locked) {
            event(new UserLocked($user));

            $this->auditLog->record(
                action: 'user.locked',
                target: $user,
                after: ['failed_login_attempts' => $attempts],
            );
        }

        return $user;
    }

    public function recordSuccessfulLogin(User $user, string $ip): User
    {
        $user->update([
            'failed_login_attempts' => 0,
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ]);

        return $user;
    }

    public function unlock(User $user): User
    {
        $before = ['status' => $user->status->value];

        $user->update([
            'status' => UserStatus::Active,
            'failed_login_attempts' => 0,
            'locked_at' => null,
        ]);

        $this->auditLog->record(
            action: 'user.unlocked',
            target: $user,
            before: $before,
            after: ['status' => $user->status->value],
        );

        return $user;
    }

    /**
     * Whether the user's password is older than the configured expiry
     * window. Enforcement (forcing a reset) is left to Core\Auth, this
     * only answers the question — see docs/security/policies.md.
     */
    public function passwordHasExpired(User $user): bool
    {
        if ($user->password_changed_at === null) {
            return false;
        }

        $expiryDays = (int) config('services.auth.password_expiry_days', 90);

        return $user->password_changed_at->addDays($expiryDays)->isPast();
    }
}
