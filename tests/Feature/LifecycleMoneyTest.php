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

    /**
     * A subscriber who already PAID the current period, whose NEXT charge (renewal) is now 1 day past expiry: all options open.
     * The paid charge stays completed (and in the wallet); the renewal is the held row the journey acts on.
     *
     * @return array{0:string,1:string,2:string} renewal id, subscription id, paid charge id
     */
    private function held(int $amount = 2000, ?string $email = null): array
    {
        $sub = (string) Str::uuid();
        $paid = (string) Str::uuid();
        $now = now();
        $email ??= 'a'.Str::random(4).'@x.test';
        DB::table('subscriptions')->insert(['id' => $sub, 'merchant_id' => $this->m, 'merchant_user_id' => 'u'.Str::random(4), 'email' => $email, 'provider' => 'mock',
            'provider_subscription_id' => 'I-'.Str::random(6), 'status' => 'active', 'amount_minor' => $amount, 'currency' => 'USD',
            'current_period_start' => $now->copy()->subDays(30), 'current_period_end' => $now->copy()->addDays(1), 'metadata' => '{}', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('transactions')->insert(['id' => $paid, 'merchant_id' => $this->m, 'subscription_id' => $sub, 'type' => 'charge', 'status' => 'completed', 'idempotency_key' => 'k-'.$paid,
            'merchant_user_id' => 'u1', 'email' => $email, 'amount_minor' => $amount, 'currency' => 'USD', 'provider' => 'mock', 'processed_at' => $now,
            'metadata' => json_encode(['lifecycle' => 'ACTIVE', 'display_name' => 'Ann', 'product' => 'Premium']), 'created_at' => $now, 'updated_at' => $now]);
        $journey = app(\App\Services\Luvu\RenewalJourney::class);
        $r = $journey->ensureRenewal($this->m, $paid);
        $journey->moveTo($this->m, $r->id, -25, MerchantProject::forMerchant($this->m));

        return [$r->id, $sub, $paid];
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
        $this->assertSame(2000, $k['wallet_balance_minor'], 'only the PAID current period; the renewal is not money yet');
        $this->assertSame(2000, $k['at_risk_minor']);
    }

    public function test_renew_releases_the_full_charge(): void
    {
        [$tx] = $this->held();
        $this->decide($tx, 'renew');
        $this->assertSame(4000, $this->metrics()['wallet_balance_minor'], 'paid period + the recovered renewal');
        $this->assertTrue(DB::table('subscriptions')->where('merchant_id', $this->m)->where('current_period_end', '>', now()->addDays(25))->exists(), 'next period granted');
    }

    public function test_split_pay_counts_cash_as_it_arrives_and_never_double_counts(): void
    {
        [$tx, $sub] = $this->held(2001);   // odd amount: parts must still sum exactly
        $r = $this->decide($tx, 'split_pay');
        $plan = $r['plan'];
        $this->assertSame(2001, array_sum(array_column($plan['installments'], 'amount_minor')));
        $this->assertSame(1001, $plan['paid_minor']);

        $k = $this->metrics();
        $this->assertSame(2001 + 1001, $k['wallet_balance_minor'], 'paid period + the down payment');
        $this->assertSame(1000, $k['at_risk_minor'], 'only the unpaid remainder is at stake');
        $this->assertSame('active', DB::table('subscriptions')->where('id', $sub)->value('status'));

        $done = app(SplitPayService::class)->payInstallment($this->m, $plan['plan_id'], 2, 'test');
        $this->assertTrue($done['completed']);
        $k = $this->metrics();
        $this->assertSame(2001 + 2001, $k['wallet_balance_minor'], 'completed plan = renewal once, instalments superseded');
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
        $this->assertSame(2000 + 1000, $k['wallet_balance_minor']);
        $this->assertSame('expired', DB::table('subscriptions')->where('id', $sub)->value('status'));
        $this->assertSame('cancelled', DB::table('transactions')->where('id', $tx)->value('status'));
        $this->assertFalse(app(SplitPayService::class)->defaultPlan($this->m, $plan['plan_id']), 'idempotent');
    }

    public function test_partial_refund_nets_the_wallet_and_cancels_the_subscription(): void
    {
        [$tx, $sub] = $this->held();
        $this->decide($tx, 'partial_refund');
        $k = $this->metrics();
        $this->assertSame(2000 + 1600, $k['wallet_balance_minor']);
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
        $this->assertSame(2000 + 1600, $k['wallet_balance_minor']);
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
        $this->assertSame(2000, $this->metrics()['wallet_balance_minor'], 'unsold listing: only the paid period counts');
        $this->assertLessThanOrEqual(2000, (int) $listing->asking_price_minor);

        DB::transaction(fn () => app(ResaleSettlement::class)->settle($listing, 'buyer@x.test', 'b1', 'mock', 'REF1', 'test'));
        $k = $this->metrics();
        $this->assertSame(4000, $k['wallet_balance_minor'], 'paid period + the released renewal, the platform fee is not merchant money');
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
        [$tx, $sub] = $this->held(2000);
        DB::table('transactions')->where('id', $tx)->update(['second_chance_expires_at' => now()->subHour()]);
        $this->assertSame(1, app(GraceDecisionService::class)->sweepExpired());
        $this->assertSame('cancelled', DB::table('transactions')->where('id', $tx)->value('status'));
        $this->assertSame(2000, $this->metrics()['wallet_balance_minor'], 'the paid period is never touched');
        $this->assertSame('expired', DB::table('subscriptions')->where('id', $sub)->value('status'));
    }

    public function test_withdrawals_reserve_the_balance_and_failed_ones_release_it(): void
    {
        [$tx] = $this->held();
        $this->decide($tx, 'renew');   // wallet 4000
        $row = fn (string $status) => DB::table('transactions')->insert(['id' => (string) Str::uuid(), 'merchant_id' => $this->m, 'type' => 'payout', 'status' => $status,
            'idempotency_key' => 'p-'.Str::random(8), 'amount_minor' => -500, 'currency' => 'USD', 'created_at' => now(), 'updated_at' => now()]);
        $row('pending');
        $this->assertSame(3500, $this->metrics()['wallet_balance_minor']);
        $row('failed');
        $this->assertSame(3500, $this->metrics()['wallet_balance_minor'], 'failed payout does not hold money');
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

    public function test_reminder_stages_allow_only_pay_split_or_cancel_and_the_renewal_is_not_money(): void
    {
        [$tx, $sub, $paid] = $this->held();
        $journey = app(\App\Services\Luvu\RenewalJourney::class);
        foreach ([168 => 'd7', 72 => 'd3', 48 => 'h48', 6 => 'h6'] as $hours => $stage) {
            $plan = $journey->moveTo($this->m, $tx, $hours, MerchantProject::forMerchant($this->m))['plan'];
            $this->assertSame($stage, $plan['stage']);
            $this->assertSame('pending', DB::table('transactions')->where('id', $tx)->value('status'));
            $this->assertSame(['renew', 'split_pay', 'expire'], array_column(array_filter($plan['options'], fn ($o) => $o['available']), 'id'), $stage.': only pay now, split, cancel');
            $this->assertNotEmpty($plan['reminder']['body'] ?? null, 'a reminder is generated for '.$stage);
            foreach (['partial_refund', 'resale', 'donate'] as $locked) {
                try {
                    $this->decide($tx, $locked);
                    $this->fail($locked.' must be locked at '.$stage);
                } catch (\RuntimeException $e) {
                    $this->assertStringContainsString('not available at this stage', $e->getMessage());
                }
            }
            $this->assertSame(2000, $this->metrics()['wallet_balance_minor'], 'paid period untouched at '.$stage);
        }
        $this->assertSame('completed', DB::table('transactions')->where('id', $paid)->value('status'));
    }

    public function test_paying_early_extends_the_subscription_and_adds_the_next_period_to_the_wallet(): void
    {
        [$tx, $sub] = $this->held();
        app(\App\Services\Luvu\RenewalJourney::class)->moveTo($this->m, $tx, 72, MerchantProject::forMerchant($this->m));
        $end = DB::table('subscriptions')->where('id', $sub)->value('current_period_end');
        $this->decide($tx, 'renew');
        $this->assertSame(4000, $this->metrics()['wallet_balance_minor']);
        $this->assertTrue(\Illuminate\Support\Carbon::parse(DB::table('subscriptions')->where('id', $sub)->value('current_period_end'))->gt(\Illuminate\Support\Carbon::parse($end)->addDays(25)));
        $this->assertSame('active', DB::table('subscriptions')->where('id', $sub)->value('status'));
    }

    public function test_cancelling_the_renewal_in_time_bills_nothing_and_keeps_access_to_the_end_of_the_period(): void
    {
        [$tx, $sub] = $this->held();
        app(\App\Services\Luvu\RenewalJourney::class)->moveTo($this->m, $tx, 168, MerchantProject::forMerchant($this->m));
        $end = DB::table('subscriptions')->where('id', $sub)->value('current_period_end');
        $r = $this->decide($tx, 'expire');
        $this->assertStringContainsString('Nothing was billed', $r['message']);
        $this->assertSame('cancelled', DB::table('subscriptions')->where('id', $sub)->value('status'));
        $this->assertSame($end, DB::table('subscriptions')->where('id', $sub)->value('current_period_end'), 'access runs to the original expiry');
        $this->assertSame(2000, $this->metrics()['wallet_balance_minor']);
    }

    public function test_splitting_the_next_payment_before_expiry(): void
    {
        [$tx, $sub] = $this->held();
        app(\App\Services\Luvu\RenewalJourney::class)->moveTo($this->m, $tx, 48, MerchantProject::forMerchant($this->m));
        $plan = $this->decide($tx, 'split_pay')['plan'];
        $this->assertSame(2000 + 1000, $this->metrics()['wallet_balance_minor']);
        $this->assertSame('active', DB::table('subscriptions')->where('id', $sub)->value('status'));
        $this->assertTrue(app(\App\Services\Luvu\SplitPayService::class)->payInstallment($this->m, $plan['plan_id'], 2)['completed']);
        $this->assertSame(4000, $this->metrics()['wallet_balance_minor']);
    }

    public function test_options_are_locked_until_one_day_after_expiry_then_all_open(): void
    {
        [$tx, $sub] = $this->held();
        DB::table('subscriptions')->where('id', $sub)->update(['current_period_end' => now()->subHours(10)]);   // lapsed: payment failed, options still locked
        try {
            $this->decide($tx, 'renew');
            $this->fail('locked in the first day');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('not available at this stage', $e->getMessage());
        }
        DB::table('subscriptions')->where('id', $sub)->update(['current_period_end' => now()->subHours(25)]);   // +1 day: everything opens
        $plan = app(\App\Services\Luvu\GraceStagePlanner::class)->plan(DB::table('transactions')->find($tx), DB::table('subscriptions')->find($sub), MerchantProject::forMerchant($this->m), 'Ann');
        $this->assertSame('grace_open', $plan['stage']);
        $this->assertCount(6, array_filter($plan['options'], fn ($o) => $o['available']));
        $by = array_column($plan['options'], null, 'id');
        $this->assertSame(1600, $by['partial_refund']['effect']['wallet_delta_minor']);
        $this->assertSame(2000, $by['split_pay']['effect']['wallet_delta_minor'] + $by['split_pay']['effect']['later_minor']);
        $this->decide($tx, 'resale');
    }

    public function test_reminders_are_sent_once_per_stage_by_email_and_stored_for_the_phone(): void
    {
        [$tx] = $this->held(2000, 'real.person@gmail.com');
        \Illuminate\Support\Facades\Mail::fake();   // count only what the journey sends from here on
        $journey = app(\App\Services\Luvu\RenewalJourney::class);
        $plan = $journey->moveTo($this->m, $tx, 168, MerchantProject::forMerchant($this->m))['plan'];
        $this->assertSame('sent', $plan['reminder']['email']['status']);
        $this->assertSame('template', $plan['reminder']['source']);   // no Nebius key in tests: honest fallback
        $this->assertStringContainsString('$20.00', $plan['reminder']['body']);
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\PlainHtmlMail::class, 1);

        $journey->moveTo($this->m, $tx, 168, MerchantProject::forMerchant($this->m));   // same stage again: no second email
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\PlainHtmlMail::class, 1);
        $journey->moveTo($this->m, $tx, 72, MerchantProject::forMerchant($this->m));
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\PlainHtmlMail::class, 2);
    }

    public function test_fake_demo_addresses_never_get_email(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        [$tx] = $this->held(2000, 'buyer@demo.luvupay.local');
        $plan = app(\App\Services\Luvu\RenewalJourney::class)->moveTo($this->m, $tx, 48, MerchantProject::forMerchant($this->m))['plan'];
        $this->assertSame('skipped_demo_address', $plan['reminder']['email']['status']);
        \Illuminate\Support\Facades\Mail::assertNothingSent();
    }

    public function test_scheduler_moves_a_due_renewal_into_grace_and_sends_the_reminder(): void
    {
        [$tx, $sub] = $this->held(2000, 'real.person@gmail.com');
        $journey = app(\App\Services\Luvu\RenewalJourney::class);
        $journey->moveTo($this->m, $tx, 6, MerchantProject::forMerchant($this->m));          // pending, 6h before expiry
        DB::table('subscriptions')->where('id', $sub)->update(['current_period_end' => now()->subMinute()]);   // time passes: expiry reached
        $journey->tick();
        $this->assertSame('held', DB::table('transactions')->where('id', $tx)->value('status'));
        $this->assertSame('past_due', DB::table('subscriptions')->where('id', $sub)->value('status'));
        $meta = json_decode(DB::table('transactions')->where('id', $tx)->value('metadata'), true);
        $this->assertArrayHasKey('lapsed', $meta['reminders'], 'the "payment failed" notice goes out by itself');
        $this->assertSame(2000, $this->metrics()['wallet_balance_minor']);
    }

    private function actingAsProject(): void
    {
        // MonthSimulator reads the project through MerchantProject::current() (auth/session); log a merchant user in.
        $uid = (string) Str::uuid();
        DB::table('merchant_users')->insert(['id' => $uid, 'merchant_id' => $this->m, 'name' => 'O', 'email' => 'o@acme.test', 'password' => bcrypt('x'), 'role' => 'owner', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs(\App\Models\MerchantUser::find($uid), 'merchant_web');
    }
}
