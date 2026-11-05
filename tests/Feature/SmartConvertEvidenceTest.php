<?php

namespace Tests\Feature;

use App\Models\MerchantUser;
use App\Models\SmartConvertRun;
use App\Services\PayPal\PayPalClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** Hero flow: the AI path is labelled honestly, failures are loud, PayPal ids are never invented, humans can intervene. */
class SmartConvertEvidenceTest extends BaseTestCase
{
    use RefreshDatabase;

    private MockHandler $paypalQueue;
    private array $session = [];

    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = ['mfa_passed' => true, 'mfa_verified_at' => time()];
        config([
            'nebius.enabled' => true, 'nebius.api_key' => 'test-key', 'nebius.retries' => 1,
            'nebius.base_url' => 'https://nebius.test/v1', 'nebius.model' => 'nvidia/NVIDIA-Nemotron-3-Nano-30B-A3B',
            'luvupay.smart_convert.strict' => true, 'luvupay.smart_convert.default_mode' => 'auto', 'luvupay.demo_simulator' => true,
        ]);
    }

    private function user(): MerchantUser
    {
        $m = (string) Str::uuid();
        DB::table('merchants')->insert(['id' => $m, 'name' => 'Acme', 'email' => 'owner@acme.test', 'currency' => 'USD', 'created_at' => now(), 'updated_at' => now(),
            'paypal_merchant_id' => 'SBM'.Str::random(8), 'paypal_connected_at' => now(),   // required once the driver is paypal
            'project_json' => json_encode(['name' => 'Acme', 'price_minor' => 2000, 'currency' => 'USD'])]);
        $id = (string) Str::uuid();
        DB::table('merchant_users')->insert(['id' => $id, 'merchant_id' => $m, 'name' => 'Owner', 'email' => 'owner@acme.test', 'password' => bcrypt('x'), 'role' => 'owner',
            'mfa_secret' => 'x', 'mfa_enabled_at' => now(), 'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return MerchantUser::find($id);
    }

    private function fakeNebius(array $answer, int $tokens = 412): void
    {
        Http::fake(['nebius.test/*' => Http::response([
            'model' => 'nvidia/NVIDIA-Nemotron-3-Nano-30B-A3B',
            'choices' => [['message' => ['content' => json_encode($answer)], 'finish_reason' => 'stop']],
            'usage' => ['total_tokens' => $tokens],
        ], 200)]);
    }

    private function realPaypal(): void
    {
        config(['luvupay.payment.driver' => 'paypal', 'services.paypal.client_id' => 'cid', 'services.paypal.secret' => 'sec',
            'services.paypal.mode' => 'sandbox', 'services.paypal.charity_payout_email' => 'charity@sandbox.test']);
        $json = fn (array $b) => new PsrResponse(200, ['Content-Type' => 'application/json'], json_encode($b));
        $this->paypalQueue = new MockHandler([
            $json(['access_token' => 'tok', 'expires_in' => 3600, 'token_type' => 'Bearer']),
            $json(['batch_header' => ['payout_batch_id' => 'REALBATCH123', 'batch_status' => 'PENDING']]),
            $json(['batch_header' => ['payout_batch_id' => 'REALBATCH123', 'batch_status' => 'SUCCESS']]),
        ]);
        $http = new Client(['handler' => HandlerStack::create($this->paypalQueue), 'base_uri' => PayPalClient::SANDBOX_URL, 'http_errors' => false]);
        $this->app->instance(PayPalClient::class, new PayPalClient($http, 'cid', 'sec', null, []));
    }

    private function good(): array
    {
        return ['store_credit_minor' => 1700, 'charity_minor' => 900, 'fee_minor' => 400, 'risk_score' => 72,
            'rationale' => ['Inactive 20 days: refund would raise chargeback risk', 'Store credit keeps the customer in the ecosystem', 'Charity share builds goodwill'],
            'narrative' => 'Mostly store credit with a meaningful donation.'];
    }

    private function convert(MerchantUser $u, array $body = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($u, 'merchant_web')->withSession($this->session)->postJson('/api/v1/dashboard/demo/smart-convert', $body);
    }

    public function test_ai_path_returns_model_tokens_latency_rationale_split_and_real_batch_id(): void
    {
        $this->fakeNebius($this->good());
        $this->realPaypal();

        $r = $this->convert($this->user())->assertOk()->assertJsonPath('ok', true);

        $r->assertJsonPath('path', 'ai')
            ->assertJsonPath('status', 'executed')
            ->assertJsonPath('ai.name', 'nvidia/NVIDIA-Nemotron-3-Nano-30B-A3B')
            ->assertJsonPath('ai.tokens_used', 412)
            ->assertJsonPath('ai.live', true)
            ->assertJsonPath('conversion.store_credit_minor', 1700)
            ->assertJsonPath('conversion.charity_minor', 900)
            ->assertJsonPath('paypal.real', true)
            ->assertJsonPath('paypal.payout_batch_id', 'REALBATCH123')
            ->assertJsonPath('paypal.batch_status', 'SUCCESS');
        $this->assertIsInt($r->json('ai.latency_ms'));
        $this->assertCount(3, $r->json('ai.rationale'));
        $this->assertSame(0, $this->paypalQueue->count(), 'token + create + verify calls were all made');

        // the charity ledger row carries the REAL batch id, not a made-up one
        $this->assertSame('REALBATCH123', DB::table('transactions')->where('type', 'charity_payout')->value('provider_reference'));
    }

    public function test_evidence_page_is_public_screenshot_ready_and_has_no_pii(): void
    {
        $this->fakeNebius($this->good());
        $this->realPaypal();
        $r = $this->convert($this->user())->assertOk();

        $page = $this->get($r->json('evidence_url'))->assertOk();
        $html = $page->getContent();
        foreach (['AI Negotiation Evidence', 'nvidia/NVIDIA-Nemotron-3-Nano-30B-A3B', '412', 'REALBATCH123', 'REAL PayPal Payouts batch', 'Charity share builds goodwill', 'PayPal Payouts executes'] as $needle) {
            $this->assertStringContainsString($needle, $html, $needle);
        }
        $this->assertStringNotContainsString('charity@sandbox.test', $html);
        $this->assertStringContainsString('ch***@sandbox.test', $html);   // masked

        $json = $this->getJson($r->json('evidence_json_url'))->assertOk()->json();
        $this->assertSame('REALBATCH123', $json['paypal']['payout_batch_id']);
        $this->assertStringNotContainsString('owner@acme.test', json_encode($json));
        $this->assertStringNotContainsString('owner@acme.test', $html);
        $this->assertSame('o***@acme.test', $json['audit']['decided_by']);
    }

    public function test_strict_mode_fails_loudly_when_ai_is_down_and_no_money_moves(): void
    {
        Http::fake(['nebius.test/*' => Http::response(['error' => 'boom'], 500)]);
        $this->realPaypal();

        $r = $this->convert($this->user())->assertStatus(502);
        $r->assertJsonPath('ok', false)->assertJsonPath('stage', 'ai');
        $this->assertSame(3, $this->paypalQueue->count(), 'PayPal must not be called when the AI step failed');
        $this->assertSame(0, DB::table('transactions')->where('type', 'charity_payout')->count());
        $this->assertSame('failed', SmartConvertRun::first()->status);
        $this->get($r->json('evidence_url'))->assertOk()->assertSee('FAILED: no money moved');
    }

    public function test_non_strict_fallback_is_labelled_and_never_claims_ai_or_real_payout(): void
    {
        config(['luvupay.smart_convert.strict' => false]);
        Http::fake(['nebius.test/*' => Http::response(['error' => 'down'], 500)]);

        $r = $this->convert($this->user())->assertOk();
        $r->assertJsonPath('path', 'fallback')->assertJsonPath('ai.live', false)->assertJsonPath('paypal.real', false);
        $this->assertStringContainsString('FALLBACK PATH', $r->json('headline'));
        $this->get($r->json('evidence_url'))->assertSee('NOT produced by the AI model', false);
    }

    public function test_mock_driver_is_labelled_simulated_with_no_invented_batch_id(): void
    {
        $this->fakeNebius($this->good());   // default test driver = mock

        $r = $this->convert($this->user())->assertOk();
        $r->assertJsonPath('paypal.simulated', true)->assertJsonPath('paypal.real', false)->assertJsonPath('paypal.payout_batch_id', null);
        $this->assertStringContainsString('SIMULATED', $r->json('headline'));
    }

    public function test_model_output_is_clamped_to_policy_and_every_adjustment_is_recorded(): void
    {
        // fee 40% (cap 15%) and amounts sum to 3300 instead of 3000
        $this->fakeNebius(['store_credit_minor' => 1000, 'charity_minor' => 1100, 'fee_minor' => 1200, 'rationale' => ['x']]);

        $r = $this->convert($this->user())->assertOk();
        $c = $r->json('conversion');
        $this->assertSame(3000, $c['store_credit_minor'] + $c['charity_minor'] + $c['fee_minor']);
        $this->assertLessThanOrEqual(450, $c['fee_minor']);
        $this->assertSame(['store_credit_minor' => 1000, 'charity_minor' => 1100, 'fee_minor' => 1200], $c['model_raw']);   // untouched
        $this->assertNotEmpty($c['guardrail_adjustments']);
    }

    public function test_propose_mode_moves_no_money_until_the_merchant_approves_and_can_lower_the_donation(): void
    {
        $this->fakeNebius($this->good());
        $this->realPaypal();
        $u = $this->user();

        $p = $this->convert($u, ['mode' => 'propose'])->assertStatus(202);
        $p->assertJsonPath('status', 'proposed');
        $this->assertSame(3, $this->paypalQueue->count(), 'no PayPal call before approval');
        $this->assertSame(0, DB::table('transactions')->where('type', 'charity_payout')->count());

        $a = $this->actingAs($u, 'merchant_web')->withSession($this->session)
            ->postJson('/api/v1/dashboard/demo/smart-convert/'.$p->json('run_id').'/approve', ['charity_minor' => 500])->assertOk();
        $a->assertJsonPath('status', 'executed')->assertJsonPath('conversion.charity_minor', 500)->assertJsonPath('conversion.store_credit_minor', 2100)
            ->assertJsonPath('paypal.payout_batch_id', 'REALBATCH123');
        $this->assertStringContainsString('Merchant lowered the donation', implode(' ', $a->json('conversion.guardrail_adjustments')));

        // a second decision on the same proposal is refused
        $this->actingAs($u, 'merchant_web')->withSession($this->session)
            ->postJson('/api/v1/dashboard/demo/smart-convert/'.$p->json('run_id').'/approve')->assertStatus(409);
    }

    public function test_merchant_can_reject_and_a_rejected_run_never_pays(): void
    {
        $this->fakeNebius($this->good());
        $this->realPaypal();
        $u = $this->user();
        $p = $this->convert($u, ['mode' => 'propose'])->assertStatus(202);

        $this->actingAs($u, 'merchant_web')->withSession($this->session)
            ->postJson('/api/v1/dashboard/demo/smart-convert/'.$p->json('run_id').'/reject')->assertOk()->assertJsonPath('status', 'rejected');
        $this->assertSame(3, $this->paypalQueue->count());
        $this->actingAs($u, 'merchant_web')->withSession($this->session)
            ->postJson('/api/v1/dashboard/demo/smart-convert/'.$p->json('run_id').'/approve')->assertStatus(409);
    }

    public function test_another_merchant_cannot_approve_my_proposal(): void
    {
        $this->fakeNebius($this->good());
        $p = $this->convert($this->user(), ['mode' => 'propose'])->assertStatus(202);

        DB::table('merchants')->where('email', 'owner@acme.test')->update(['email' => 'first@acme.test']);
        $other = $this->user();
        $this->actingAs($other, 'merchant_web')->withSession($this->session)
            ->postJson('/api/v1/dashboard/demo/smart-convert/'.$p->json('run_id').'/approve')->assertStatus(404);
    }

    public function test_dashboard_renders_the_run_button(): void
    {
        $html = $this->actingAs($this->user(), 'merchant_web')->withSession($this->session)->get('/dashboard')->assertOk()->getContent();
        $this->assertStringContainsString('Run AI Smart-Convert Protocol', $html);
        $this->assertStringContainsString('/js/smart-convert.js', $html);
    }

    public function test_seeded_placeholder_charity_wallet_never_overrides_the_env_receiver_or_is_used_as_one(): void
    {
        DB::table('charity_wallets')->insert(['id' => (string) Str::uuid(), 'name' => 'Seed', 'payout_email' => 'donations@charity.example.org', 'currency' => 'USD', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->fakeNebius($this->good());
        $this->realPaypal();
        $r = $this->convert($this->user())->assertOk();
        $this->assertSame('ch***@sandbox.test', $r->json('paypal.receiver_masked'));   // env address won

        // with no env address, the placeholder wallet is refused: loud PayPal-stage failure, nothing paid
        config(['services.paypal.charity_payout_email' => null]);
        $this->fakeNebius($this->good());
        $this->convert($this->user())->assertStatus(502)->assertJsonPath('stage', 'paypal');
    }

    public function test_demo_check_says_no_go_on_the_mock_driver_and_go_when_everything_is_real(): void
    {
        $this->artisan('luvupay:demo-check')->expectsOutputToContain('NO-GO')->assertFailed();

        $this->realPaypal();
        $this->user();
        config(['services.paypal.webhook_id' => 'WH', 'services.paypal.merchant_payout_email' => 'm@sandbox.test']);
        $this->artisan('luvupay:demo-check')->expectsOutputToContain('GO: no blocking problems')->assertSuccessful();
    }

    public function test_donation_stage_is_visible_labelled_as_an_example_and_names_no_real_charity(): void
    {
        $this->fakeNebius($this->good());   // mock driver: nothing real moves

        $r = $this->convert($this->user())->assertOk();
        $r->assertJsonPath('donation.example_only', true)
            ->assertJsonPath('donation.status', 'simulated')
            ->assertJsonPath('donation.recipient', 'Example Charity (sandbox)')
            ->assertJsonPath('donation.amount_minor', 900);
        $this->assertStringStartsWith('DON-', $r->json('donation.receipt_id'));

        // the ledger row carries the same example label, never a real organisation
        $this->assertSame('Example Charity (sandbox)', DB::table('transactions')->where('type', 'charity_payout')->value('email'));

        $html = $this->get($r->json('evidence_url'))->assertOk()->getContent();
        foreach (['5 · Donation', 'EXAMPLE · SANDBOX', 'Example Charity (sandbox)', 'No real organisation is paid'] as $needle) {
            $this->assertStringContainsString($needle, $html, $needle);
        }
    }

    public function test_donation_waits_for_approval_and_is_not_sent_when_rejected(): void
    {
        $this->fakeNebius($this->good());
        $u = $this->user();

        $p = $this->convert($u, ['mode' => 'propose'])->assertStatus(202);
        $p->assertJsonPath('donation.status', 'awaiting_approval');

        $this->actingAs($u, 'merchant_web')->withSession($this->session)
            ->postJson('/api/v1/dashboard/demo/smart-convert/'.$p->json('run_id').'/reject')->assertOk();
        $json = $this->getJson($p->json('evidence_json_url'))->assertOk()->json();
        $this->assertSame('not_sent', $json['donation']['status']);
    }
}
