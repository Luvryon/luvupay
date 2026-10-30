<?php

namespace Tests\Feature;

use App\Http\Controllers\Demo\DemoCheckoutController;
use App\Services\Checkout\CheckoutFinalizer;
use App\Services\Checkout\PendingCheckoutActivator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** New Subscription Checkout: mock mode end-to-end + the race / vanished-row cases that used to surface as "Transaction not found." */
class CheckoutFlowTest extends BaseTestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    private string $merchantId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->merchantId = (string) Str::uuid();
        DB::table('merchants')->insert([
            'id' => $this->merchantId, 'name' => 'Acme', 'email' => 'owner@acme.test',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function req(string $method, string $uri, array $data = []): Request
    {
        $r = Request::create($uri, $method, $data, [], [], ['HTTP_ACCEPT' => 'application/json']);
        $r->attributes->set('merchant_id', $this->merchantId);

        return $r;
    }

    private function controller(): DemoCheckoutController
    {
        return $this->app->make(DemoCheckoutController::class);   // fresh instance per call, like a real request
    }

    /** A pending PayPal-style checkout exactly as startCheckout() leaves it. */
    private function seedPending(string $email = 'buyer@example.com'): array
    {
        $subId = (string) Str::uuid();
        $txId = (string) Str::uuid();
        $now = now();
        DB::table('subscriptions')->insert([
            'id' => $subId, 'merchant_id' => $this->merchantId, 'merchant_user_id' => 'u1', 'email' => $email,
            'provider' => 'paypal', 'provider_subscription_id' => 'I-REAL123', 'status' => 'pending_approval',
            'amount_minor' => 2000, 'currency' => 'USD', 'metadata' => '{}', 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('transactions')->insert([
            'id' => $txId, 'merchant_id' => $this->merchantId, 'subscription_id' => $subId, 'type' => 'charge',
            'status' => 'pending', 'idempotency_key' => 'k-'.$txId, 'merchant_user_id' => 'u1', 'email' => $email,
            'amount_minor' => 2000, 'currency' => 'USD', 'provider' => 'paypal', 'provider_reference' => 'I-REAL123',
            'metadata' => json_encode(['source' => 'developer_sandbox', 'display_name' => 'Buyer', 'product' => 'Acme Premium', 'awaiting_paypal' => true, 'paypal_kind' => 'subscription', 'paypal_subscription_id' => 'I-REAL123']),
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return [$txId, $subId];
    }

    public function test_mock_checkout_is_active_immediately_with_one_invoice(): void
    {
        $res = $this->controller()->simulateCheckout($this->req('POST', '/x', ['email' => 'sarah@example.com', 'display_name' => 'Sarah']));
        $json = $res->getData(true);

        $this->assertSame(201, $res->getStatusCode(), json_encode($json));
        $this->assertTrue($json['ok']);
        $this->assertSame('mock', $json['mode']);
        $this->assertSame('completed', $json['row']['status']);
        $this->assertNotNull($json['row']['invoice_number']);

        $txId = $json['transaction_id'];
        $this->assertSame(1, DB::table('invoices')->where('transaction_id', $txId)->count());
        $this->assertSame('active', DB::table('subscriptions')->where('email', 'sarah@example.com')->value('status'));

        // Polling the status of a finished checkout must not issue a second invoice / email.
        $st = $this->controller()->checkoutStatus($this->req('GET', '/s'), $txId)->getData(true);
        $this->assertSame('captured', $st['state']);
        $this->assertSame(1, DB::table('invoices')->where('transaction_id', $txId)->count());
    }

    public function test_webhook_activation_still_issues_invoice_exactly_once(): void
    {
        [$txId] = $this->seedPending();

        // 1) PayPal webhook wins the race against the return URL.
        $won = app(PendingCheckoutActivator::class)->activateFromWebhook(['custom_id' => $txId, 'provider_subscription_id' => 'I-REAL123']);
        $this->assertTrue($won);
        $this->assertSame('completed', DB::table('transactions')->where('id', $txId)->value('status'));
        $this->assertSame(1, DB::table('invoices')->where('transaction_id', $txId)->count(), 'webhook path must issue the invoice');

        // 2) The dashboard poll arrives afterwards: reports captured, does NOT duplicate anything.
        $st = $this->controller()->checkoutStatus($this->req('GET', '/s'), $txId)->getData(true);
        $this->assertSame('captured', $st['state']);
        $this->assertSame(1, DB::table('invoices')->where('transaction_id', $txId)->count());

        // 3) Finalizer is idempotent.
        $again = app(CheckoutFinalizer::class)->finalize($this->merchantId, $txId);
        $this->assertFalse($again['finalized']);
        $this->assertSame('ACTIVE', json_decode(DB::table('transactions')->where('id', $txId)->value('metadata'), true)['lifecycle']);
    }

    public function test_status_of_a_vanished_checkout_is_gone_not_a_404(): void
    {
        $txId = (string) Str::uuid();   // never existed / already discarded
        $res = $this->controller()->checkoutStatus($this->req('GET', '/s'), $txId);
        $d = $res->getData(true);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('gone', $d['state']);
        $this->assertSame($txId, $d['removed_id']);
        $this->assertNull($d['row']);
    }

    public function test_status_of_another_merchants_checkout_is_not_leaked(): void
    {
        [$txId] = $this->seedPending();
        $other = (string) Str::uuid();
        DB::table('merchants')->insert(['id' => $other, 'name' => 'Other', 'created_at' => now(), 'updated_at' => now()]);
        $r = Request::create('/s', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $r->attributes->set('merchant_id', $other);

        $d = $this->app->make(DemoCheckoutController::class)->checkoutStatus($r, $txId)->getData(true);
        $this->assertSame('gone', $d['state']);
        $this->assertSame('pending', DB::table('transactions')->where('id', $txId)->value('status'));
    }

    public function test_cancel_discards_pending_rows_and_status_then_reports_gone(): void
    {
        [$txId, $subId] = $this->seedPending();

        $this->controller()->paypalCancel($this->req('GET', '/dashboard/paypal/cancel', ['tx' => $txId, 'popup' => 1]));
        $this->assertSame(0, DB::table('transactions')->where('id', $txId)->count());
        $this->assertSame(0, DB::table('subscriptions')->where('id', $subId)->count());

        $d = $this->controller()->checkoutStatus($this->req('GET', '/s'), $txId)->getData(true);
        $this->assertSame('gone', $d['state']);
    }

    public function test_paypal_driver_without_credentials_is_a_clear_422(): void
    {
        config(['luvupay.payment.driver' => 'paypal', 'services.paypal.client_id' => '', 'services.paypal.secret' => '']);
        $res = $this->controller()->simulateCheckout($this->req('POST', '/x', ['email' => 'a@b.co']));

        $this->assertSame(422, $res->getStatusCode());
        $this->assertSame('paypal_not_configured', $res->getData(true)['error']);
        $this->assertSame(0, DB::table('transactions')->count());
    }
}
