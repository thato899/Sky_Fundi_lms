<?php

declare(strict_types=1);

namespace Core\Auth\Application;

use Core\AuditLogs\Application\AuditLogService;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;

/**
 * Enrollment and verification for TOTP-based two-factor authentication
 * (see Totp for the RFC 6238 algorithm). The only place
 * users.two_factor_* columns are read or written — set via forceFill()
 * rather than added to User::$fillable, so they can never become
 * mass-assignable through a stray request-driven update() elsewhere.
 * Login-time gating lives in TwoFactorLoginService, not here.
 */
final class TwoFactorAuthenticationService
{
    private const RECOVERY_CODE_COUNT = 8;

    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * Starts (or restarts) enrollment: generates a new secret and clears
     * any previous confirmation/recovery codes. The secret does not gate
     * login until confirm() succeeds — an abandoned enrollment is inert.
     */
    public function generateSecret(User $user): string
    {
        $secret = Totp::generateSecret();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        return $secret;
    }

    public function provisioningUri(User $user, string $issuer = 'Sky Fundi'): string
    {
        $secret = $user->getAttribute('two_factor_secret');
        if (! is_string($secret)) {
            throw new DomainException('No pending two-factor secret exists for this account.');
        }

        return Totp::provisioningUri($secret, $user->getAttribute('email'), $issuer);
    }

    /**
     * Confirms enrollment with a code from the authenticator app,
     * generates one-time recovery codes, and returns them — this is the
     * only time the plaintext recovery codes are ever available; the
     * caller must show them to the user immediately.
     *
     * @return list<string>
     */
    public function confirm(User $user, string $code): array
    {
        $secret = $user->getAttribute('two_factor_secret');
        if (! is_string($secret) || ! Totp::verify($secret, $code)) {
            throw new DomainException('The provided code is invalid or has expired.');
        }

        $codes = $this->newRecoveryCodes();
        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $codes,
        ])->save();
        $this->audit->record(action: 'auth.two_factor_enabled', target: $user);

        return $codes;
    }

    public function verify(User $user, string $code): bool
    {
        $secret = $user->getAttribute('two_factor_secret');

        return $user->hasTwoFactorEnabled() && is_string($secret) && Totp::verify($secret, $code);
    }

    /** Recovery codes are single-use: a successful match consumes it. */
    public function verifyRecoveryCode(User $user, string $code): bool
    {
        if (! $user->hasTwoFactorEnabled()) {
            return false;
        }
        $codes = $user->getAttribute('two_factor_recovery_codes') ?? [];
        $normalized = strtoupper(trim($code));
        $index = array_search($normalized, $codes, true);
        if ($index === false) {
            return false;
        }

        unset($codes[$index]);
        $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();
        $this->audit->record(action: 'auth.two_factor_recovery_code_used', target: $user);

        return true;
    }

    /** @return list<string> */
    public function regenerateRecoveryCodes(User $user): array
    {
        if (! $user->hasTwoFactorEnabled()) {
            throw new DomainException('Two-factor authentication is not enabled for this account.');
        }
        $codes = $this->newRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();
        $this->audit->record(action: 'auth.two_factor_recovery_codes_regenerated', target: $user);

        return $codes;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
        $this->audit->record(action: 'auth.two_factor_disabled', target: $user);
    }

    /** @return list<string> */
    private function newRecoveryCodes(): array
    {
        return array_map(
            fn () => strtoupper(substr(bin2hex(random_bytes(5)), 0, 5).'-'.substr(bin2hex(random_bytes(5)), 0, 5)),
            range(1, self::RECOVERY_CODE_COUNT),
        );
    }
}
