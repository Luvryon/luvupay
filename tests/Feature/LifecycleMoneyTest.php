<?php

namespace Tests\Feature;

use App\Services\Dashboard\MerchantMetrics;
use App\Services\Luvu\GraceDecisionService;
use App\Services\Luvu\SplitPayService;
use App\Services\Resale\ResaleSettlement;
use App\Support\MerchantProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Money rules of the missed-cancellation journey: wallet, split pay, resale, charity, refunds, double-resolution guards. */
class LifecycleMoneyTest extends BaseTestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    private string $m;

    protected function setUp(): void
    {
        parent::setUp();
        $this->m = (string) Str::uuid();
        DB::table('merchants')->insert([
            'id' => $this->m, 'name' => 'Acme', 'email' => 'o@acme.test', 'currency' => 'USD', 'created_at' => now(), 'updated_at' => now(),
            'project_json' => json_encode(['name' => 'Acme', 'plan' => 'Premium', 'price_minor' => 2000, 'currency' => 'USD', 'marketplace_resale' => true, 'charity_split' => true, 'grace_period' => true, 'partial_refund_bps' => 2000, 'charity_bps' => 2000]),
        ]);
    }

    /** A charge in its grace period, exactly as "Missed cancellation" leaves it. @return array{0:string,1:string} */
    private function held(int $amount = 2000, ?string $expires = null): array
    {
        $sub = (string) Str::uuid();
        $tx = (string) Str::uuid();
        $now = now();
        DB::table('subscriptions')->insert(['id' => $sub, 'merchant_id' => $this->m, 'merchant_user_id' => 'u'.Str::random(4), 'email' => 'a'.Str::random(4).'@x.test', 'provider' => 'mock',
            'provider_subscription_id' => 'I-'.Str::random(6), 'status' => 'past_due', 'amount_minor' => $amount, 'currency' => 'USD',
            'current_period_start' => $now->copy()->subDays(10), 'current_period_end' => $now->copy()->addDays(20), 'metadata' => '{}', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('transactions')->insert(['id' => $tx, 'merchant_id' => $this->m, 'subscription_id' => $sub, 'type' => 'charge', 'status' => 'held', 'idempotency_key' => 'k-'.$tx,
            'merchant_user_id' => 'u1', 'email' => 'a@x.test', 'amount_minor' => $amount, 'currency' => 'USD', 'provider' => 'mock', 'processed_at' => $now,
            'second_chance_expires_at' => $expires ?? $now->copy()->addHours(48),
            'metadata' => json_encode(['lifecycle' => 'GRACE PERIOD', 'display_name' => 'Ann', 'grace' => ['partial_refund_bps' => 2000]]), 'created_at' => $now, 'updated_at' => $now]);

        return [$tx, $sub];
    }

    private function decide(string $tx, string $choice, string $source = 'user'): array
    {
        return app(GraceDecisionService::class)->decide($this->m, $tx, $choice, MerchantProject::forMerchant($this->m), $source);
    }

    private function metrics(): array
    {
        return app(MerchantMetrics::class)->compute($this->m);
    }

    public function test_held_charge_is_not_in_the_wallet_until_resolved(): void
    {
        $this->held();
        $k = $this->metrics();
        $this->assertSame(0, $k['wallet_balance_minor']);
        $this->assertSame(2000, $k['at_risk_minor']);
    }

    public function test_renew_releases_the_full_charge(): void
    {
        [$tx] = $this->held();
        $this->decide($tx, 'renew');
        $this->assertSame(2000, $this->metrics()['wallet_balance_minor']);
    }

    public function test_split_pay_counts_cash_as_it_arrives_and_never_double_counts(): void
    {
        [$tx, $sub] = $this->held(2001);   // odd amount: parts must still sum exactly
        $r = $this->decide($tx, 'split_pay');
        $plan = $r['plan'];
        $this->assertSame(2001, array_sum(array_column($plan['installments'], 'amount_minor')));
        $this->assertSame(1001, $plan['paid_minor']);

        $k = $this->metrics();
        $this->assertSame(1001, $k['wallet_balance_minor'], 'only the down payment is spendable');
        $this->assertSame(1000, $k['at_risk_minor'], 'only the unpaid remainder is at stake');
        $this->assertSame('active', DB::table('subscriptions')->where('id', $sub)->value('status'));

        $done = app(SplitPayService::class)->payInstallment($this->m, $plan['plan_id'], 2, 'test');
        $this->assertTrue($done['completed']);
        $k = $this->metrics();
        $this->assertSame(2001, $k['wallet_balance_minor'], 'completed plan = charge once, instalments superseded');
        $this->assertSame(0, $k['at_risk_minor']);
        $this->assertSame('completed', DB::table('transactions')->where('id', $tx)->value('status'));

        $this->expectException(\RuntimeException::class);   // nothing left to collect: no double charge
        app(SplitPayService::class)->payInstallment($this->m, $plan['plan_id'], null, 'test');
    }

    public function test_split_pay_default_keeps_collected_cash_and_ends_access(): void
    {
        [$tx, $sub] = $this->held(2000);
        $plan = $this->decide($tx, 'split_pay')['plan'];
        $this->assertTrue(app(SplitPayService::class)->defaultPlan($this->m, $plan['plan_id']));
        $k = $this->metrics();
        $this->assertSame(1000, $k['wallet_balance_minor']);
        $this->assertSame('expired', DB::table('subscriptions')->where('id', $sub)->value('status'));
        $this->assertSame('cancelled', DB::table('transactions')->where('id', $tx)->value('status'));
        $this->assertFalse(app(SplitPayService::class)->defaultPlan($this->m, $plan['plan_id']), 'idempotent');
    }

    public function test_partial_refund_nets_the_wallet_and_cancels_the_subscription(): void
    {
        [$tx, $sub] = $this->held();
        $this->decide($tx, 'partial_refund');
        $k = $this->metrics();
        $this->assertSame(1600, $k['wallet_balance_minor']);
        $this->assertSame(400, $k['refunded_minor']);
        $this->assertSame(1600, $k['revenue_saved_minor']);
        $this->assertSame('cancelled', DB::table('subscriptions')->where('id', $sub)->value('status'));
    }

    public function test_a_resolved_charge_cannot_be_decided_twice(): void
    {
        [$tx] = $this->held();
        $this->decide($tx, 'partial_refund');
        $this->expectException(\RuntimeException::class);
        $this->decide($tx, 'donate');
    }

    public function test_donation_is_the_charity_share_only_and_happens_once(): void
    {
        [$tx] = $this->held();
        $this->decide($tx, 'donate');
        $k = $this->metrics();
        $this->assertSame(400, $k['charity_minor']);
        $this->assertSame(1600, $k['wallet_balance_minor']);
        $this->assertSame(1, DB::table('transactions')->where('type', 'charity_payout')->count());
        $this->expectException(\RuntimeException::class);
        $this->decide($tx, 'donate');
    }

    public function test_resale_releases_the_charge_and_the_platform_fee_is_not_merchant_money(): void
    {
        [$tx, $sub] = $this->held();
        $this->decide($tx, 'resale');
        $listing = DB::table('resale_listings')->where('subscription_id', $sub)->where('status', 'active')->first();
        $this->assertNotNull($listing);
        $this->assertSame(0, $this->metrics()['wallet_balance_minor'], 'unsold listing: still held');
        $this->assertLessThanOrEqual(2000, (int) $listing->asking_price_minor);

        DB::transaction(fn () => app(ResaleSettlement::class)->settle($listing, 'buyer@x.test', 'b1', 'mock', 'REF1', 'test'));
        $k = $this->metrics();
        $this->assertSame(2000, $k['wallet_balance_minor'], 'merchant keeps the original charge, nothing more, nothing less');
        $ask = (int) $listing->asking_price_minor;
        $fee = $ask - (int) round($ask * 0.9);
        $this->assertSame($ask, $k['resale_volume_minor']);
        $this->assertSame($fee, $k['platform_fee_minor'], 'fee counted once (sale - payout), not twice');
        $this->assertSame(1, $k['outcomes']['resale']);
        $this->assertSame('resold', DB::table('subscriptions')->where('id', $sub)->value('status'));

        $this->expectException(\RuntimeException::class);   // a second buyer loses the race
        DB::transaction(fn () => app(ResaleSettlement::class)->settle($listing, 'other@x.test', 'b2', 'mock', 'REF2', 'test'));
    }

    public function test_choosing_something_else_voids_the_open_listing(): void
    {
        [$tx, $sub] = $this->held();
        $this->decide($tx, 'resale');
        $this->decide($tx, 'renew');
        $this->assertSame(0, DB::table('resale_listings')->where('subscription_id', $sub)->where('status', 'active')->count());
    }

    public function test_sweeper_expires_closed_windows_and_voids_listings(): void
    {
        [$tx, $sub] = $this->held(2000, now()->subHour()->toDateTimeString());
        $this->assertSame(1, app(GraceDecisionService::class)->sweepExpired());
        $this->assertSame('cancelled', DB::table('transactions')->where('id', $tx)->value('status'));
        $this->assertSame(0, $this->metrics()['wallet_balance_minor']);
    }

    public function test_withdrawals_reserve_the_balance_and_failed_ones_release_it(): void
    {
        [$tx] = $this->held();
        $this->decide($tx, 'renew');
        $row = fn (string $status) => DB::table('transactions')->insert(['id' => (string) Str::uuid(), 'merchant_id' => $this->m, 'type' => 'payout', 'status' => $status,
            'idempotency_key' => 'p-'.Str::random(8), 'amount_minor' => -500, 'currency' => 'USD', 'created_at' => now(), 'updated_at' => now()]);
        $row('pending');
        $this->assertSame(1500, $this->metrics()['wallet_balance_minor']);
        $row('failed');
        $this->assertSame(1500, $this->metrics()['wallet_balance_minor'], 'failed payout does not hold money');
    }

    public function test_simulate_30_days_produces_a_consistent_ledger(): void
    {
        $this->actingAsProject();
        $r = app(\App\Services\Demo\MonthSimulator::class)->run($this->m);
        $this->assertSame(12, $r['created']);
        $k = $this->metrics();
        $this->assertGreaterThanOrEqual(0, $k['wallet_raw_minor'], 'wallet can never be negative');
        $this->assertFalse($k['wallet_inconsistent']);
        $this->assertSame(1, $k['outcomes']['split_pay']);
        $this->assertSame(0, DB::table('resale_listings')->where('status', 'active')->where('access_expires_at', '<=', now())->count());
        // every sold listing: sale = payout + fee (derived), and no charity leg on resale
        $this->assertSame(0, DB::table('transactions')->where('type', 'charity_payout')->where('metadata', 'like', '%resale_charity_leg%')->count());
        $this->assertSame($k['resale_volume_minor'] - DB::table('transactions')->where('type', 'resale_payout')->sum('amount_minor'), $k['platform_fee_minor']);
        // wallet == independent recomputation straight from the rows
        $charges = (int) DB::table('transactions')->where('type', 'charge')->whereIn('status', ['completed', 'settled'])->sum('amount_minor');
        $inst = (int) DB::table('transactions')->where('type', 'installment')->where('status', 'completed')->sum('amount_minor');
        $charity = (int) DB::table('transactions')->where('type', 'charity_payout')->where('status', 'completed')->sum('amount_minor');
        $refunds = 0;
        foreach (DB::table('transactions')->where('type', 'charge')->get() as $c) {
            $meta = json_decode($c->metadata, true);
            if (($meta['decision'] ?? '') === 'partial_refund_accepted') {
                $refunds += (int) $meta['smart_convert']['refund_minor'];
            }
        }
        $this->assertSame($charges + $inst - $charity - $refunds, $k['wallet_balance_minor']);
    }

    public function test_stage_rules_close_options_as_the_deadline_approaches(): void
    {
        [$d7] = $this->held(2000, now()->addHours(100)->toDateTimeString());
        try {
            $this->decide($d7, 'partial_refund');
            $this->fail('partial refund must be closed at 7 days');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('closed at this stage', $e->getMessage());
        }
        $this->decide($d7, 'split_pay');   // allowed at 7 days

        [$d3, $sub3] = $this->held(2000, now()->addHours(60)->toDateTimeString());
        $this->decide($d3, 'resale');      // allowed at 3 days
        $this->assertSame(1, DB::table('resale_listings')->where('subscription_id', $sub3)->where('status', 'active')->count());

        [$h6] = $this->held(2000, now()->addHours(5)->toDateTimeString());
        foreach (['resale', 'donate', 'partial_refund'] as $closed) {
            try {
                $this->decide($h6, $closed);
                $this->fail($closed.' must be closed at 6 hours');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('closed at this stage', $e->getMessage());
            }
        }
        $this->decide($h6, 'renew');
    }

    public function test_planner_describes_each_stage_and_projects_the_money(): void
    {
        [$tx, $sub] = $this->held(2000, now()->addHours(60)->toDateTimeString());
        $plan = app(\App\Services\Luvu\GraceStagePlanner::class)->plan(DB::table('transactions')->find($tx), DB::table('subscriptions')->find($sub), MerchantProject::forMerchant($this->m), 'Ann');
        $this->assertSame('d3', $plan['stage']);
        $by = array_column($plan['options'], null, 'id');
        $this->assertTrue($by['resale']['available']);
        $this->assertSame(1600, $by['partial_refund']['effect']['wallet_delta_minor']);
        $this->assertSame(2000, $by['split_pay']['effect']['wallet_delta_minor'] + $by['split_pay']['effect']['later_minor']);
        $this->assertStringContainsString('Ann', $plan['message']);

        DB::table('transactions')->where('id', $tx)->update(['second_chance_expires_at' => now()->addHours(3)]);
        $plan = app(\App\Services\Luvu\GraceStagePlanner::class)->plan(DB::table('transactions')->find($tx), DB::table('subscriptions')->find($sub), MerchantProject::forMerchant($this->m), 'Ann');
        $this->assertSame('h6', $plan['stage']);
        $this->assertFalse(array_column($plan['options'], null, 'id')['resale']['available']);
    }

    private function actingAsProject(): void
    {
        // MonthSimulator reads the project through MerchantProject::current() (auth/session); log a merchant user in.
        $uid = (string) Str::uuid();
        DB::table('merchant_users')->insert(['id' => $uid, 'merchant_id' => $this->m, 'name' => 'O', 'email' => 'o@acme.test', 'password' => bcrypt('x'), 'role' => 'owner', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs(\App\Models\MerchantUser::find($uid), 'merchant_web');
    }
}
