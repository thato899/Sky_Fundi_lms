<?php

declare(strict_types=1);

namespace Core\Security\Application;

use Core\Security\Infrastructure\Models\TwoFactorAuthentication;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

final class TwoFactorAuthenticationService
{
    public function begin(User $user): string
    {
        $secret = $this->base32Encode(random_bytes(20));

        TwoFactorAuthentication::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'secret' => Crypt::encryptString($secret),
                'recovery_codes' => $this->newRecoveryCodes(),
                'confirmed_at' => null,
            ],
        );

        return $secret;
    }

    /** @return array{secret: string, recovery_codes: list<string>} */
    public function confirm(User $user, string $code): array
    {
        $record = $this->record($user);

        if ($record === null || ! $this->validCode(Crypt::decryptString($record->secret), $code)) {
            throw new \InvalidArgumentException('The authentication code is invalid.');
        }

        $record->forceFill(['confirmed_at' => now()])->save();

        return [
            'secret' => Crypt::decryptString($record->secret),
            'recovery_codes' => $record->recovery_codes,
        ];
    }

    public function enabled(User $user): bool
    {
        return ($record = $this->record($user)) !== null && $record->confirmed_at !== null;
    }

    public function verify(User $user, string $code): bool
    {
        $record = $this->record($user);

        if ($record === null || $record->confirmed_at === null) {
            return false;
        }

        $secret = Crypt::decryptString($record->secret);
        if ($this->validCode($secret, $code)) {
            return true;
        }

        $codes = $record->recovery_codes ?? [];
        $index = array_search(Str::upper(trim($code)), $codes, true);
        if ($index === false) {
            return false;
        }

        unset($codes[$index]);
        $record->forceFill(['recovery_codes' => array_values($codes)])->save();

        return true;
    }

    private function record(User $user): ?TwoFactorAuthentication
    {
        return TwoFactorAuthentication::query()->find($user->id);
    }

    private function validCode(string $secret, string $code): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $counter = intdiv(now()->timestamp, 30);
        for ($offset = -1; $offset <= 1; $offset++) {
            if (hash_equals($this->hotp($secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    private function hotp(string $secret, int $counter): string
    {
        $binaryCounter = pack('N2', intdiv($counter, 0x100000000), $counter & 0xFFFFFFFF);
        $hash = hash_hmac('sha1', $binaryCounter, $this->base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($value % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    /** @return list<string> */
    private function newRecoveryCodes(): array
    {
        return array_map(static fn (): string => Str::upper(Str::random(4).'-'.Str::random(4)), range(1, 8));
    }

    private function base32Encode(string $value): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (unpack('C*', $value) as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
        }

        return $encoded;
    }

    private function base32Decode(string $value): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split(Str::upper($value)) as $character) {
            $bits .= str_pad(decbin(strpos($alphabet, $character)), 5, '0', STR_PAD_LEFT);
        }

        $result = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $result .= chr(bindec($chunk));
            }
        }

        return $result;
    }
}
