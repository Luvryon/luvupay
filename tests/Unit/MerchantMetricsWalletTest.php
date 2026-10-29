<?php

namespace Tests\Unit;

use App\Services\Dashboard\MerchantMetrics;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class MerchantMetricsWalletTest extends TestCase
{
    public function test_resale_fees_helper_is_non_negative(): void
    {
        $m = new MerchantMetrics;
        $ref = new ReflectionMethod(MerchantMetrics::class, 'resaleFees');
        $ref->setAccessible(true);
        $fees = $ref->invoke($m, [
            'resale_volume' => 1000,
            'resale_payouts' => 900,
            'resale_listing_fees' => 0,
        ]);
        $this->assertSame(100, $fees);
    }
}
