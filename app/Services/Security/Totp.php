<?php

namespace App\Services\Security;

/** RFC 6238 TOTP (HMAC-SHA1, 30 s, 6 digits) — compatible with Google Authenticator, 1Password, Authy, etc. */
final class Totp
{
    public const PERIOD = 30;
    public const DIGITS = 6;
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function currentStep(?int $time = null): int
    {
        return intdiv($time ?? time(), self::PERIOD);
    }

    public static function code(string $base32Secret, int $step): string
    {
        $key = self::base32Decode($base32Secret);
        $hash = hash_hmac('sha1', pack('J', $step), $key, true);
        $o = ord($hash[19]) & 0x0F;
        $bin = ((ord($hash[$o]) & 0x7F) << 24) | (ord($hash[$o + 1]) << 16) | (ord($hash[$o + 2]) << 8) | ord($hash[$o + 3]);

        return str_pad((string) ($bin % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * @return int|null the matched time step (store it as "last used"), or null when invalid / replayed.
     */
    public static function verify(string $base32Secret, string $code, ?int $lastUsedStep = null, int $window = 1, ?int $time = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (! preg_match('/^\d{'.self::DIGITS.'}$/', $code)) {
            return null;
        }
        $now = self::currentStep($time);
        for ($i = -$window; $i <= $window; $i++) {
            $step = $now + $i;
            if ($lastUsedStep !== null && $step <= $lastUsedStep) {
                continue;                                   // a code can be used once
            }
            if (hash_equals(self::code($base32Secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    public static function uri(string $secret, string $account, string $issuer = 'LuvuPay'): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer.':'.$account).'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits='.self::DIGITS.'&period='.self::PERIOD;
    }

    public static function base32Encode(string $bin): string
    {
        $bits = '';
        foreach (str_split($bin) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public static function base32Decode(string $s): string
    {
        $s = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s) ?? '');
        $bits = '';
        foreach (str_split($s) as $c) {
            $bits .= str_pad(decbin((int) strpos(self::ALPHABET, $c)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}
