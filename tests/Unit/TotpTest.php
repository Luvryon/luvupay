<?php

namespace Tests\Unit;

use App\Services\Security\Totp;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    private function secret(): string
    {
        return Totp::base32Encode('12345678901234567890');   // RFC 6238 test key
    }

    public function test_matches_rfc6238_vectors(): void
    {
        $this->assertSame('287082', Totp::code($this->secret(), Totp::currentStep(59)));
        $this->assertSame('081804', Totp::code($this->secret(), Totp::currentStep(1111111109)));
        $this->assertSame('279037', Totp::code($this->secret(), Totp::currentStep(2000000000)));
    }

    public function test_a_code_cannot_be_replayed(): void
    {
        $step = Totp::verify($this->secret(), '287082', null, 1, 59);
        $this->assertNotNull($step);
        $this->assertNull(Totp::verify($this->secret(), '287082', $step, 1, 59));
    }

    public function test_rejects_malformed_and_wrong_codes(): void
    {
        $this->assertNull(Totp::verify($this->secret(), 'abcdef', null, 1, 59));
        $this->assertNull(Totp::verify($this->secret(), '000000', null, 1, 59));
        $this->assertNull(Totp::verify($this->secret(), '12345', null, 1, 59));
    }

    public function test_base32_round_trip(): void
    {
        $s = Totp::generateSecret();
        $this->assertSame($s, Totp::base32Encode(Totp::base32Decode($s)));
    }
}
