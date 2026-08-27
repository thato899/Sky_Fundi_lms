<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use Core\Auth\Application\Totp;
use Tests\TestCase;

final class TotpTest extends TestCase
{
    /**
     * RFC 4226 Appendix D reference HOTP vectors for the shared secret
     * ASCII "12345678901234567890", counters 0-9. TOTP (RFC 6238) is
     * HOTP with counter = floor(time / step), so verifying the raw HOTP
     * output against these official vectors proves the HMAC-SHA1
     * truncation and Base32 decode are both correct, independent of the
     * time-window logic tested separately below.
     */
    public function test_hotp_matches_the_rfc_4226_reference_vectors(): void
    {
        $secret = self::base32Encode('12345678901234567890');
        $expected = ['755224', '287082', '359152', '969429', '338314', '254676', '287922', '162583', '399871', '520489'];

        foreach ($expected as $counter => $code) {
            $this->assertSame($code, Totp::hotp($secret, $counter), "counter {$counter}");
        }
    }

    public function test_generate_then_verify_round_trips_at_the_current_time(): void
    {
        $secret = Totp::generateSecret();
        $code = Totp::generate($secret);

        $this->assertTrue(Totp::verify($secret, $code));
    }

    public function test_verify_tolerates_one_step_of_clock_drift_but_not_two(): void
    {
        $secret = Totp::generateSecret();
        $now = 1_700_000_000; // fixed reference instant
        $code = Totp::generate($secret, $now);

        $this->assertTrue(Totp::verify($secret, $code, $now + 30));
        $this->assertTrue(Totp::verify($secret, $code, $now - 30));
        $this->assertFalse(Totp::verify($secret, $code, $now + 90));
    }

    public function test_verify_rejects_a_wrong_or_malformed_code(): void
    {
        $secret = Totp::generateSecret();

        $this->assertFalse(Totp::verify($secret, '000000'));
        $this->assertFalse(Totp::verify($secret, 'not-a-code'));
        $this->assertFalse(Totp::verify($secret, '12345'));
    }

    public function test_provisioning_uri_carries_the_secret_issuer_and_account(): void
    {
        $secret = Totp::generateSecret();
        $uri = Totp::provisioningUri($secret, 'user@example.test', 'Sky Fundi');

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret='.$secret, $uri);
        $this->assertStringContainsString(rawurlencode('Sky Fundi'), $uri);
        $this->assertStringContainsString(rawurlencode('user@example.test'), $uri);
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
}
