<?php

namespace Tests\Feature;

use App\Jobs\SettleMarketplaceSaleJob;
use App\Mail\PlainHtmlMail;
use App\Services\Marketplace\MarketplaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/** Seller is paid automatically after a sale, but only to an address whose owner proved control of the inbox. */
class MarketplaceSellerPayoutTest extends BaseTestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['luvupay.marketplace.require_seller_verification' => false, 'luvupay.marketplace.auto_payout' => true,
            'luvupay.marketplace.require_verified_for_payout' => true, 'luvupay.marketplace.seller_bps' => 9000]);
    }

    /** list → buy → PayPal return, all through HTTP. Returns the listing id. */
    private function sellAndBuy(string $seller = 'seller@sandbox.test'): string
    {
        $r = $this->postJson('/api/marketplace/sell', [
            'app_key' => 'netflix', 'app_name' => 'Netflix', 'title' => '12 days of Netflix', 'description' => 'Unused days',
            'seller_email' => $seller, 'days_remaining' => 12, 'original_price_minor' => 1500, 'price_minor' => 1000,
        ])->assertStatus(201);
        $id = $r->json('listing.id');

        $buy = $this->postJson("/api/marketplace/listings/{$id}/buy", ['buyer_email' => 'buyer@sandbox.test'])->assertOk();
        $this->get($buy->json('approve_url'))->assertRedirect('/marketplace?bought=1');

        return $id;
    }

    private function row(string $id): object
    {
        return DB::table('marketplace_listings')->where('id', $id)->first();
    }

    public function test_listing_is_never_marked_verified_just_because_verification_is_disabled(): void
    {
        $id = $this->postJson('/api/marketplace/sell', [
            'app_key' => 'x', 'app_name' => 'X', 'title' => 'T', 'description' => 'D', 'seller_email' => 's@x.test',
            'days_remaining' => 3, 'original_price_minor' => 500, 'price_minor' => 300,
        ])->assertStatus(201)->json('listing.id');

        $this->assertNull($this->row($id)->verified_at);
        $this->assertNull($this->row($id)->ownership_level);
    }

    public function test_unverified_seller_payout_is_held_and_the_signed_link_is_emailed(): void
    {
        $id = $this->sellAndBuy();

        $l = $this->row($id);
        $this->assertSame('sold', $l->status);
        $this->assertSame('held_unverified', $l->payout_status);
        $this->assertNull($l->payout_batch_id, 'no money moved to an unverified address');
        $this->assertSame(0, DB::table('ledger_journals')->where('kind', 'marketplace_payout')->count());
        Mail::assertSent(PlainHtmlMail::class, fn ($m) => str_contains($m->bodyHtml, 'Confirm and release my payout') && str_contains($m->bodyHtml, '/verify?'));
    }

    public function test_verifying_the_link_grants_ownership_and_releases_the_payout_exactly_once(): void
    {
        $id = $this->sellAndBuy();
        $link = URL::temporarySignedRoute('marketplace.verify', now()->addDay(), ['id' => $id, 'e' => MarketplaceService::emailFingerprint('seller@sandbox.test')]);

        $this->get($link)->assertRedirect('/marketplace?verified=1');

        $l = $this->row($id);
        $this->assertSame('email_signed_link', $l->ownership_level);
        $this->assertNotNull($l->verified_at);
        $this->assertSame('paid', $l->payout_status);
        $this->assertSame(900, (int) $l->payout_minor);               // 90% of 1000
        $this->assertNotEmpty($l->payout_batch_id);
        $this->assertTrue((bool) $l->payout_simulated, 'mock driver is labelled simulated');
        $this->assertSame(1, DB::table('ledger_journals')->where('reference', "marketplace_payout:{$id}")->count());

        $ev = json_decode($l->ownership_evidence, true);
        $this->assertSame('signed_link_sent_to_seller_email', $ev['method']);
        $this->assertStringContainsString('does_not_prove', json_encode($ev));
        $this->assertStringNotContainsString('127.0.0.1', json_encode($ev), 'raw IP is never stored');

        // clicking again, or a duplicate job, must not pay twice
        $this->get($link);
        SettleMarketplaceSaleJob::dispatchSync($id);
        $this->assertSame(1, (int) $this->row($id)->payout_attempts);
        $this->assertSame(1, DB::table('ledger_journals')->where('reference', "marketplace_payout:{$id}")->count());
    }

    public function test_link_issued_for_another_address_is_rejected(): void
    {
        $id = $this->sellAndBuy();
        $link = URL::temporarySignedRoute('marketplace.verify', now()->addDay(), ['id' => $id, 'e' => MarketplaceService::emailFingerprint('attacker@evil.test')]);

        $this->get($link)->assertRedirect('/marketplace?failed=1');
        $this->assertNull($this->row($id)->ownership_level);
        $this->assertSame('held_unverified', $this->row($id)->payout_status);
    }

    public function test_unsigned_verify_url_is_forbidden(): void
    {
        $id = $this->sellAndBuy();
        $this->get("/marketplace/listings/{$id}/verify?e=".MarketplaceService::emailFingerprint('seller@sandbox.test'))->assertStatus(403);
        $this->assertNull($this->row($id)->ownership_level);
    }

    public function test_verified_at_listing_time_pays_automatically_after_the_sale(): void
    {
        config(['luvupay.marketplace.require_seller_verification' => true]);
        $r = $this->postJson('/api/marketplace/sell', [
            'app_key' => 'netflix', 'app_name' => 'Netflix', 'title' => 'T', 'description' => 'D', 'seller_email' => 'seller@sandbox.test',
            'days_remaining' => 5, 'original_price_minor' => 1500, 'price_minor' => 1000,
        ])->assertStatus(202);
        $id = $r->json('listing.id');
        $this->get(URL::temporarySignedRoute('marketplace.verify', now()->addDay(), ['id' => $id, 'e' => MarketplaceService::emailFingerprint('seller@sandbox.test')]))->assertRedirect('/marketplace?verified=1');
        $this->assertSame('active', $this->row($id)->status);

        $buy = $this->postJson("/api/marketplace/listings/{$id}/buy", ['buyer_email' => 'buyer@sandbox.test'])->assertOk();
        $this->get($buy->json('approve_url'))->assertRedirect('/marketplace?bought=1');

        $this->assertSame('paid', $this->row($id)->payout_status, 'no extra step: sale → payout');
        Mail::assertSent(PlainHtmlMail::class, fn ($m) => str_contains($m->bodyHtml, 'Payout reference'));
    }

    public function test_maintenance_requeues_a_sold_listing_whose_payout_never_ran(): void
    {
        config(['luvupay.marketplace.auto_payout' => false]);       // simulate a worker that was down at sale time
        $id = $this->sellAndBuy();
        DB::table('marketplace_listings')->where('id', $id)->update([
            'ownership_level' => 'email_signed_link', 'verified_at' => now(), 'sold_at' => now()->subMinutes(10)]);
        $this->assertNull($this->row($id)->payout_status);

        config(['luvupay.marketplace.auto_payout' => true]);
        $this->artisan('resale:maintain')->assertSuccessful();

        $this->assertSame('paid', $this->row($id)->payout_status);
    }
}
