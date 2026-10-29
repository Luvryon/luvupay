<?php

namespace Tests\Feature;

use App\Support\Tenant;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TenantIsolationTest extends TestCase
{
    public function test_tenant_id_requires_merchant_context(): void
    {
        $this->expectException(HttpException::class);
        Tenant::id(Request::create('/api/v1/dashboard/summary', 'GET'));
    }

    public function test_tenant_id_reads_request_attributes(): void
    {
        $request = Request::create('/api/v1/dashboard/summary', 'GET');
        $request->attributes->set('merchant_id', '11111111-1111-1111-1111-111111111111');
        $this->assertSame('11111111-1111-1111-1111-111111111111', Tenant::id($request));
    }
}
