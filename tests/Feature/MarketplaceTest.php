<?php

namespace Tests\Feature;

use App\Services\Ledger\LedgerPoster;
use App\Services\PayPal\PayPalClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.paypal.client_id' => 'cid', 'services.paypal.secret' => 'sec',
            'luvupay.marketplace.require_seller_verification' => false,
            'luvupay.demo_simulator' => false,
        ]);
        Mail::fake();
    }

    private function listing(array $o = []): string
    {
        $id = (string) Str::uuid();
        DB::table('marketplace_listings')->insert(array_merge([
            'id' => $id, 'app_key' => 'streamflix', 'app_name' => 'StreamFlix', 'title' => 'StreamFlix 15d',
            'description' => 'x', 'seller_email' => 'seller@example.com', 'days_remaining' => 15,
            'original_price_minor' => 2000, 'price_minor' => 1000, 'currency' => 'USD', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ], $o));

        return $id;
    }

    private function row(string $id): object
    {
        return DB::table('marketplace_listings')->where('id', $id)->first();
    }

    private function paypal(?callable $configure = null): MockInterface
    {
        return $this->mock(PayPalClient::class, $configure ?? fn () => null);
    }

    private function orderResponse(): array
    {
        return ['id' => 'ORDER1', 'links' => [['rel' => 'payer-action', 'href' => 'https://paypal.test/approve']]];
    }

    private function captureResponse(string $status = 'COMPLETED', string $value = '10.00'): array
    {
        return ['status' => $status, 'purchase_units' => [['payments' => ['captures' => [[
            'id' => 'CAP1', 'status' => $status, 'amount' => ['currency_code' => 'USD', 'value' => $value],
        ]]]]]];
    }

    /** Reserve through the API and return [id, rt]. */
    private function reserve(string $id): string
    {
        $this->paypal(fn ($m) => $m->shouldReceive('createOrder')->andReturn($this->orderResponse()));
        $this->postJson("/api/marketplace/listings/{$id}/buy", ['buyer_email' => 'buyer@example.com'])->assertOk();

        return $this->row($id)->reservation_token;
    }

    public function test_public_listing_never_exposes_the_sellers_email(): void
    {
        $this->listing();
        $this->listing(['status' => 'pending']);
        $this->listing(['status' => 'sold']);

        $res = $this->getJson('/api/marketplace/listings')->assertOk();

        $this->assertCount(1, $res->json('data'));
        $this->assertStringNotContainsString('seller@example.com', $res->getContent());
        $this->assertSame('s***@example.com', $res->json('data.0.seller'));
    }

    public function test_index_does_not_invent_listings_outside_demo_mode(): void
    {
        $this->getJson('/api/marketplace/listings')->assertOk()->assertJson(['data' => []]);
        $this->assertDatabaseCount('marketplace_listings', 0);
    }

    public function test_sell_rejects_a_price_above_the_original_price(): void
    {
        $this->postJson('/api/marketplace/sell', [
            'app_key' => 'streamflix', 'app_name' => 'StreamFlix', 'title' => 'T', 'description' => 'D',
            'seller_email' => 'a@b.co', 'days_remaining' => 5, 'original_price_minor' => 1000, 'price_minor' => 1500,
        ])->assertStatus(422)->assertJsonValidationErrors('price_minor');
    }

    public function test_listing_stays_hidden_until_the_seller_confirms_the_signed_link(): void
    {
        config(['luvupay.marketplace.require_seller_verification' => true]);

        $res = $this->postJson('/api/marketplace/sell', [
            'app_key' => 'streamflix', 'app_name' => 'StreamFlix', 'title' => 'T', 'description' => 'D',
            'seller_email' => 'a@b.co', 'days_remaining' => 5, 'original_price_minor' => 2000, 'price_minor' => 1000,
        ])->assertStatus(202)->assertJson(['needs_verification' => true]);

        $id = $res->json('listing.id');
        $this->getJson('/api/marketplace/listings')->assertJson(['data' => []]);

        $this->get("/marketplace/listings/{$id}/verify")->assertForbidden();   // unsigned link is refused
        $this->get(URL::temporarySignedRoute('marketplace.verify', now()->addHour(), ['id' => $id]))
            ->assertRedirect('/marketplace?verified=1');

        $this->assertSame('active', $this->row($id)->status);
        $this->assertCount(1, $this->getJson('/api/marketplace/listings')->json('data'));
    }

    public function test_buy_reserves_the_listing_and_a_second_buyer_is_refused(): void
    {
        $id = $this->listing();
        $this->reserve($id);

        $this->assertSame('reserved', $this->row($id)->status);
        $this->assertSame('ORDER1', $this->row($id)->paypal_order_id);
        $this->postJson("/api/marketplace/listings/{$id}/buy", ['buyer_email' => 'other@example.com'])->assertStatus(409);
    }

    public function test_seller_cannot_buy_their_own_listing(): void
    {
        $id = $this->listing();
        $this->postJson("/api/marketplace/listings/{$id}/buy", ['buyer_email' => 'SELLER@example.com'])->assertStatus(422);
        $this->assertSame('active', $this->row($id)->status);
    }

    public function test_provider_failure_releases_the_reservation(): void
    {
        $id = $this->listing();
        $this->paypal(fn ($m) => $m->shouldReceive('createOrder')->andThrow(new \RuntimeException('boom')));

        $this->postJson("/api/marketplace/listings/{$id}/buy", ['buyer_email' => 'buyer@example.com'])
            ->assertStatus(502);

        $row = $this->row($id);
        $this->assertSame('active', $row->status);
        $this->assertNull($row->reservation_token);
    }

    public function test_abandoned_reservation_returns_to_the_pool(): void
    {
        $id = $this->listing();
        $this->reserve($id);

        $this->travel(config('luvupay.marketplace.reservation_minutes') + 1)->minutes();

        $this->assertCount(1, $this->getJson('/api/marketplace/listings')->json('data'));
        $this->assertSame('active', $this->row($id)->status);
    }

    public function test_return_with_a_wrong_reservation_token_cannot_capture_or_sell(): void
    {
        $id = $this->listing();
        $this->reserve($id);
        $this->paypal(fn ($m) => $m->shouldNotReceive('capturePayment'));

        $this->get("/marketplace/paypal/return?listing={$id}&rt=wrong&token=ORDER1")->assertRedirect('/marketplace?failed=1');
        $this->get("/marketplace/paypal/return?listing={$id}&token=ORDER1")->assertRedirect('/marketplace?failed=1');

        $this->assertSame('reserved', $this->row($id)->status);
    }

    public function test_return_with_someone_elses_order_cannot_sell_this_listing(): void
    {
        $id = $this->listing();
        $rt = $this->reserve($id);
        $this->paypal(fn ($m) => $m->shouldNotReceive('capturePayment'));

        $this->get("/marketplace/paypal/return?listing={$id}&rt={$rt}&token=ATTACKER_ORDER")->assertRedirect('/marketplace?failed=1');
        $this->assertSame('reserved', $this->row($id)->status);
    }

    public function test_successful_capture_marks_sold_and_posts_a_balanced_ledger_journal_once(): void
    {
        $id = $this->listing();
        $rt = $this->reserve($id);
        $this->paypal(fn ($m) => $m->shouldReceive('capturePayment')->once()->andReturn($this->captureResponse()));

        $this->get("/marketplace/paypal/return?listing={$id}&rt={$rt}&token=ORDER1")->assertRedirect('/marketplace?bought=1');
        // A browser refresh must not capture or post again (capturePayment ->once() above enforces it).
        $this->get("/marketplace/paypal/return?listing={$id}&rt={$rt}&token=ORDER1")->assertRedirect('/marketplace?bought=1');

        $row = $this->row($id);
        $this->assertSame('sold', $row->status);
        $this->assertSame('CAP1', $row->capture_id);

        $ledger = app(LedgerPoster::class);
        $this->assertSame(1, DB::table('ledger_journals')->where('reference', "marketplace:{$id}")->count());
        $this->assertTrue($ledger->trialBalance()['USD']['balanced']);
        $this->assertSame(1000, $ledger->balance(LedgerPoster::CLEARING, 'USD'));
        $this->assertSame(-900, $ledger->balance(LedgerPoster::SELLER, 'USD'));
        $this->assertSame(-100, $ledger->balance(LedgerPoster::FEES, 'USD'));
    }

    public function test_capture_that_is_not_completed_or_has_the_wrong_amount_is_not_a_sale(): void
    {
        foreach ([$this->captureResponse('PENDING'), $this->captureResponse('COMPLETED', '1.00')] as $response) {
            $id = $this->listing();
            $rt = $this->reserve($id);
            $this->paypal(fn ($m) => $m->shouldReceive('capturePayment')->andReturn($response));

            $this->get("/marketplace/paypal/return?listing={$id}&rt={$rt}&token=ORDER1")->assertRedirect('/marketplace?failed=1');

            $this->assertSame('active', $this->row($id)->status);
            $this->assertSame(0, DB::table('ledger_journals')->where('reference', "marketplace:{$id}")->count());
        }
    }

    public function test_network_error_during_capture_keeps_the_reservation_for_a_retry(): void
    {
        $id = $this->listing();
        $rt = $this->reserve($id);
        $this->paypal(fn ($m) => $m->shouldReceive('capturePayment')->andThrow(new \RuntimeException('timeout')));

        $this->get("/marketplace/paypal/return?listing={$id}&rt={$rt}&token=ORDER1")->assertRedirect('/marketplace?failed=1');
        $this->assertSame('reserved', $this->row($id)->status);
    }

    public function test_only_the_matching_token_can_cancel_a_reservation(): void
    {
        $id = $this->listing();
        $rt = $this->reserve($id);

        $this->get("/marketplace/paypal/cancel?listing={$id}")->assertRedirect('/marketplace?cancelled=1');
        $this->get("/marketplace/paypal/cancel?listing={$id}&rt=wrong")->assertRedirect('/marketplace?cancelled=1');
        $this->assertSame('reserved', $this->row($id)->status);

        $this->get("/marketplace/paypal/cancel?listing={$id}&rt={$rt}")->assertRedirect('/marketplace?cancelled=1');
        $this->assertSame('active', $this->row($id)->status);
    }
}
