<?php

namespace Tests\Unit;

use App\Services\Security\Totp;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    public function test_generate_secret_is_base32(): void
    {
        $secret = Totp::generateSecret();
        $this->assertNotSame('', $secret);
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function test_verify_accepts_current_code_and_blocks_replay(): void
    {
        $secret = Totp::generateSecret();
        $step = Totp::currentStep();
        $code = Totp::code($secret, $step);
        $matched = Totp::verify($secret, $code, null, 1, $step * Totp::PERIOD);
        $this->assertSame($step, $matched);
        $this->assertNull(Totp::verify($secret, $code, $matched, 1, $step * Totp::PERIOD));
    }

    public function test_uri_contains_issuer(): void
    {
        $uri = Totp::uri('ABCDE', 'user@example.com', 'LuvuPay');
        $this->assertStringContainsString('otpauth://totp/', $uri);
        $this->assertStringContainsString('LuvuPay', $uri);
    }
}
