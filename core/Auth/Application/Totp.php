<?php

declare(strict_types=1);

namespace Core\Auth\Application;

/**
 * Time-based one-time passwords (RFC 6238, built on the HOTP algorithm
 * of RFC 4226) implemented in plain PHP — no third-party TOTP/QR
 * library dependency. Verified in tests both by self-consistency
 * (generate then verify) and against the RFC 4226 Appendix D reference
 * HOTP vectors, since TOTP is HOTP with counter = floor(time / step).
 */
final class Totp
{
    private const SECRET_BYTES = 20;

    private const DIGITS = 6;

    private const PERIOD_SECONDS = 30;

    /** A random secret, Base32-encoded for authenticator-app compatibility. */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(self::SECRET_BYTES));
    }

    /** `otpauth://` URI for manual entry or QR-code generation by the client. */
    public static function provisioningUri(string $secretBase32, string $accountLabel, string $issuer): string
    {
        $label = rawurlencode($issuer).':'.rawurlencode($accountLabel);

        return 'otpauth://totp/'.$label.'?'.http_build_query([
            'secret' => $secretBase32,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD_SECONDS,
        ]);
    }

    public static function generate(string $secretBase32, ?int $timestamp = null): string
    {
        $counter = (int) floor(($timestamp ?? time()) / self::PERIOD_SECONDS);

        return self::hotp($secretBase32, $counter);
    }

    /**
     * Accepts a code from the current time step or one step either side,
     * to tolerate ordinary clock drift between the server and the
     * authenticator app.
     */
    public static function verify(string $secretBase32, string $code, ?int $timestamp = null, int $window = 1): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $now = $timestamp ?? time();
        for ($step = -$window; $step <= $window; $step++) {
            if (hash_equals(self::generate($secretBase32, $now + ($step * self::PERIOD_SECONDS)), $code)) {
                return true;
            }
        }

        return false;
    }

    /** RFC 4226 HOTP: HMAC-SHA1 over the counter, truncated to a 6-digit code. */
    public static function hotp(string $secretBase32, int $counter): string
    {
        $key = self::base32Decode($secretBase32);
        $counterBytes = pack('N*', 0, $counter); // 8-byte big-endian counter
        $hash = hash_hmac('sha1', $counterBytes, $key, true);

        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($binary % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $binary): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($binary) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $output = '';
        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $output .= $alphabet[bindec($chunk)];
        }

        return $output;
    }

    private static function base32Decode(string $encoded): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $encoded = strtoupper(rtrim($encoded, '='));
        $bits = '';
        foreach (str_split($encoded) as $char) {
            $pos = strpos($alphabet, $char);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $binary = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) < 8) {
                continue;
            }
            $binary .= chr(bindec($byte));
        }

        return $binary;
    }
}
