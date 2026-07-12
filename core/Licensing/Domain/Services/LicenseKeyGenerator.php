<?php

declare(strict_types=1);

namespace Core\Licensing\Domain\Services;

/**
 * Pure domain logic — no persistence, no framework dependency, per
 * docs/architecture/clean-architecture.md#domain. Generates a
 * human-shareable, visually-unambiguous license key (Crockford-style
 * base32 alphabet, no 0/O/1/I/L confusion).
 */
final class LicenseKeyGenerator
{
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    private const SEGMENT_COUNT = 4;

    private const SEGMENT_LENGTH = 4;

    public function generate(string $prefix = 'SKYF'): string
    {
        $segments = [];

        for ($i = 0; $i < self::SEGMENT_COUNT; $i++) {
            $segment = '';

            for ($j = 0; $j < self::SEGMENT_LENGTH; $j++) {
                $segment .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }

            $segments[] = $segment;
        }

        return $prefix.'-'.implode('-', $segments);
    }
}
