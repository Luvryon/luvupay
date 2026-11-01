<?php

namespace Tests\Feature;

use App\Models\MerchantUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Pages render, RBAC holds on money routes, and the withdraw / decision endpoints behave under real HTTP. */
class ConsoleSmokeTest extends BaseTestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    protected function user(string $role): MerchantUser
    {
        $m = (string) Str::uuid();
        DB::table('merchants')->insert(['id' => $m, 'name' => 'Acme', 'email' => $role.'@acme.test', 'currency' => 'USD', 'created_at' => now(), 'updated_at' => now(),
            'project_json' => json_encode(['name' => 'Acme', 'price_minor' => 2000, 'currency' => 'USD', 'marketplace_resale' => true, 'charity_split' => true, 'grace_period' => true])]);
        $id = (string) Str::uuid();
        DB::table('merchant_users')->insert(['id' => $id, 'merchant_id' => $m, 'name' => ucfirst($role), 'email' => $role.'@acme.test', 'password' => bcrypt('x'), 'role' => $role,
            'mfa_secret' => 'x', 'mfa_enabled_at' => now(), 'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return MerchantUser::find($id);
    }

    public function test_console_pages_render_and_ajax_script_is_present(): void
    {
        $u = $this->user('owner');
        foreach (['/dashboard', '/wallet', '/subscriptions', '/resale', '/analytics'] as $path) {
            $r = $this->actingAs($u, 'merchant_web')->withSession(['mfa_passed' => true, 'mfa_verified_at' => time()])->get($path);
            $r->assertOk();
            $this->assertStringContainsString('/js/ajax-nav.js', $r->getContent(), $path);
            $this->assertStringContainsString('id="main"', $r->getContent());
        }
    }

    public function test_viewer_cannot_move_money(): void
    {
        $u = $this->user('viewer');
        $s = ['mfa_passed' => true, 'mfa_verified_at' => time()];
        foreach (['/api/v1/dashboard/wallet/withdraw', '/api/v1/dashboard/demo/decision', '/api/v1/dashboard/demo/split/collect', '/api/v1/dashboard/demo/resale', '/api/v1/dashboard/demo/simulate-month', '/api/v1/dashboard/project'] as $path) {
            $r = $this->actingAs($u, 'merchant_web')->withSession($s)->postJson($path, ['transaction_id' => (string) Str::uuid(), 'choice' => 'renew']);
            $this->assertSame(403, $r->getStatusCode(), $path.' must be denied for a viewer');
        }
    }

    public function test_decision_on_unknown_transaction_is_404_and_double_decision_is_409(): void
    {
        $u = $this->user('owner');
        $s = ['mfa_passed' => true, 'mfa_verified_at' => time()];
        $this->actingAs($u, 'merchant_web')->withSession($s)->postJson('/api/v1/dashboard/demo/decision', ['transaction_id' => (string) Str::uuid(), 'choice' => 'renew'])->assertStatus(404);

        $tx = (string) Str::uuid();
        DB::table('transactions')->insert(['id' => $tx, 'merchant_id' => $u->merchant_id, 'type' => 'charge', 'status' => 'completed', 'idempotency_key' => 'k'.$tx, 'amount_minor' => 2000,
            'currency' => 'USD', 'provider' => 'mock', 'metadata' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($u, 'merchant_web')->withSession($s)->postJson('/api/v1/dashboard/demo/decision', ['transaction_id' => $tx, 'choice' => 'partial_refund'])
            ->assertStatus(409)->assertJsonPath('ok', false);
    }

    public function test_withdrawals_cannot_spend_the_same_balance_twice(): void
    {
        $u = $this->user('owner');
        $s = ['mfa_passed' => true, 'mfa_verified_at' => time()];
        DB::table('transactions')->insert(['id' => (string) Str::uuid(), 'merchant_id' => $u->merchant_id, 'type' => 'charge', 'status' => 'completed', 'idempotency_key' => 'c'.Str::random(6),
            'amount_minor' => 5000, 'currency' => 'USD', 'provider' => 'mock', 'metadata' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        $call = fn (int $amt) => $this->actingAs($u, 'merchant_web')->withSession($s)->postJson('/api/v1/dashboard/wallet/withdraw', ['payout_type' => 'merchant_withdrawal', 'amount_minor' => $amt]);

        $call(3000)->assertStatus(201);
        $call(3000)->assertStatus(422);     // only 2000 left: the first withdrawal reserved its share
        $call(2000)->assertStatus(201);
        $call(1)->assertStatus(422);
        $this->assertSame(2, DB::table('transactions')->where('merchant_id', $u->merchant_id)->where('type', 'payout')->count());
        // free-form charity payouts are no longer an API
        $call2 = $this->actingAs($u, 'merchant_web')->withSession($s)->postJson('/api/v1/dashboard/wallet/withdraw', ['payout_type' => 'charity_donation', 'amount_minor' => 100]);
        $call2->assertStatus(422);
    }

    public function test_paypal_errors_are_classified_so_only_definitive_ones_fail(): void
    {
        $c = new \App\Http\Controllers\Demo\DemoCheckoutController;
        $m = new \ReflectionMethod($c, 'classifyProviderError');
        $m->setAccessible(true);
        $ex = fn (string $msg, array $ctx = [], bool $retry = false) => new \App\Exceptions\PaymentProviderException($msg, $retry, $ctx);
        $type = fn ($e) => $m->invoke($c, $e)['type'];

        $this->assertSame('declined', $type($ex('x', ['issue' => 'INSTRUMENT_DECLINED', 'status' => 422])));
        $this->assertSame('awaiting_buyer', $type($ex('x', ['issue' => 'ORDER_NOT_APPROVED', 'status' => 422])));
        $this->assertSame('already_done', $type($ex('x', ['issue' => 'ORDER_ALREADY_CAPTURED', 'status' => 422])));
        $this->assertSame('config', $type($ex('PayPal authentication failed (HTTP 401)', ['status' => 401])));
        $this->assertSame('transient', $type($ex('timeout', [], true)));
        $this->assertSame('transient', $type($ex('PayPal POST failed: HTTP 503', ['status' => 503])));
        $this->assertSame('expired', $type($ex('x', ['status' => 404])));
    }

    public function test_renewal_journey_end_to_end_over_http(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $u = $this->user('owner');
        $s = ['mfa_passed' => true, 'mfa_verified_at' => time()];
        $sub = (string) Str::uuid();
        $paid = (string) Str::uuid();
        DB::table('subscriptions')->insert(['id' => $sub, 'merchant_id' => $u->merchant_id, 'merchant_user_id' => 'u1', 'email' => 'ann@gmail.com', 'provider' => 'mock', 'provider_subscription_id' => 'I-1',
            'status' => 'active', 'amount_minor' => 2000, 'currency' => 'USD', 'current_period_end' => now()->addDays(20), 'metadata' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('transactions')->insert(['id' => $paid, 'merchant_id' => $u->merchant_id, 'subscription_id' => $sub, 'type' => 'charge', 'status' => 'completed', 'idempotency_key' => 'k'.$paid,
            'email' => 'ann@gmail.com', 'amount_minor' => 2000, 'currency' => 'USD', 'provider' => 'mock', 'metadata' => json_encode(['lifecycle' => 'ACTIVE', 'display_name' => 'Ann']), 'created_at' => now(), 'updated_at' => now()]);
        $post = fn (string $path, array $b) => $this->actingAs($u, 'merchant_web')->withSession($s)->postJson('/api/v1/dashboard/demo/'.$path, $b);

        $r = $post('forgot', ['transaction_id' => $paid, 'hours_before' => 168])->assertOk()->assertJsonPath('stage.stage', 'd7');
        $renewal = $r->json('row.id');
        $this->assertNotSame($paid, $renewal, 'the journey acts on the NEXT charge, not the paid one');
        $this->assertSame('completed', DB::table('transactions')->where('id', $paid)->value('status'));
        $this->assertNotEmpty($r->json('stage.reminder.body'));

        $post('decision', ['transaction_id' => $renewal, 'choice' => 'renew'])->assertStatus(409);            // reminders only
        $post('grace/stage', ['transaction_id' => $renewal, 'hours_before' => 48])->assertOk()->assertJsonPath('stage.stage', 'h48');
        $post('grace/stage', ['transaction_id' => $renewal, 'hours_before' => -25])->assertOk()->assertJsonPath('stage.stage', 'grace_open');
        $post('decision', ['transaction_id' => $renewal, 'choice' => 'resale'])->assertOk();
        $this->assertSame(1, DB::table('resale_listings')->where('subscription_id', $sub)->where('status', 'active')->count());
        $post('grace/stage', ['transaction_id' => $renewal, 'hours_before' => 6])->assertOk()->assertJsonPath('stage.stage', 'h6');   // back before expiry: listing withdrawn
        $this->assertSame(0, DB::table('resale_listings')->where('subscription_id', $sub)->where('status', 'active')->count());
        $post('grace/stage', ['transaction_id' => $renewal, 'hours_before' => 5])->assertStatus(422);
        $post('grace/stage', ['transaction_id' => $renewal, 'hours_before' => -49])->assertOk()->assertJsonPath('closed', true);
        $this->assertSame('cancelled', DB::table('transactions')->where('id', $renewal)->value('status'));
        $this->assertSame('completed', DB::table('transactions')->where('id', $paid)->value('status'));
        $post('grace/stage', ['transaction_id' => $renewal, 'hours_before' => 48])->assertStatus(409);
    }
}
