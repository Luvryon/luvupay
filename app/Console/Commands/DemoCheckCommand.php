<?php

namespace App\Console\Commands;

use App\Exceptions\SmartConvertException;
use App\Services\SmartConvert\SmartConvertEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Go / no-go before recording the demo. Prints PASS / WARN / FAIL per item and exits non-zero on any FAIL.
 * --live also makes one real model call (no money moves, nothing is stored except ai_request_logs).
 */
class DemoCheckCommand extends Command
{
    protected $signature = 'luvupay:demo-check {--live : also make one real AI call}';

    protected $description = 'Check that the hero demo (AI Smart-Convert → PayPal Payout, marketplace payout, webhooks) can run for real';

    private int $fails = 0;

    public function handle(SmartConvertEngine $engine): int
    {
        $this->fails = 0;   // the command instance can be reused within one process
        $this->section('AI');
        $this->row(config('nebius.enabled') && trim((string) config('nebius.api_key')) !== '', 'Nebius enabled and API key present', 'Set NEBIUS_API_KEY and NEBIUS_ENABLED=true');
        $this->row(str_contains(strtolower((string) config('nebius.model')), 'nemotron'), 'Model is Nemotron: '.config('nebius.model'), 'NEBIUS_MODEL should be a Nemotron model for the submission', warn: true);
        $this->row((bool) config('luvupay.smart_convert.strict'), 'Strict mode on (no silent fallback)', 'SMART_CONVERT_STRICT=true for recording', warn: true);

        $this->section('PayPal');
        $this->row((string) config('luvupay.payment.driver') === 'paypal', 'LUVUPAY_PAYMENT_DRIVER=paypal', 'The mock driver can only produce SIMULATED payouts');
        $this->row(! empty(config('services.paypal.client_id')) && ! empty(config('services.paypal.secret')), 'PayPal client id + secret present', 'Set PAYPAL_CLIENT_ID / PAYPAL_SECRET');
        $this->row((string) config('services.paypal.mode') === 'sandbox', 'PAYPAL_MODE=sandbox', 'Demo money must be sandbox money', warn: true);
        $charity = trim((string) config('services.paypal.charity_payout_email'));
        $this->row(filter_var($charity, FILTER_VALIDATE_EMAIL) && strcasecmp($charity, 'donations@charity.example.org') !== 0, 'PAYPAL_CHARITY_SANDBOX_EMAIL is a real sandbox account', 'Set it to your second sandbox Business account');
        $this->row(filter_var(config('services.paypal.merchant_payout_email'), FILTER_VALIDATE_EMAIL) !== false, 'PAYPAL_MERCHANT_SANDBOX_EMAIL set', 'Needed for the Withdraw-to-PayPal step', warn: true);
        $this->row(trim((string) config('services.paypal.webhook_id')) !== '', 'PAYPAL_WEBHOOK_ID set (webhooks verified, not skipped)', 'Create the webhook in the PayPal dashboard', warn: true);

        $this->row(! filter_var(config('luvupay.payout_sandbox_fallback'), FILTER_VALIDATE_BOOLEAN), 'Withdrawal fallback off (LUVUPAY_PAYOUT_SANDBOX_FALLBACK=false)', 'With it on, a PayPal rejection on Withdraw records a SANDBOX-SIM id instead of failing: never film that as a real payout', warn: true);

        $this->section('Marketplace payout');
        $this->row(config('luvupay.marketplace.auto_payout'), 'Auto payout on', 'MARKETPLACE_AUTO_PAYOUT=true');
        $this->row(config('luvupay.marketplace.require_seller_verification'), 'Sellers verified at listing time (payout never waits on a second email)', 'MARKETPLACE_REQUIRE_SELLER_VERIFICATION=true for a smooth recording', warn: true);
        $this->row(config('queue.default') !== 'sync', 'Queue is not "sync" (a worker runs payouts)', 'Use redis/database + `queue:work --queue=financial,webhooks,default`', warn: true);

        $this->section('Database');
        foreach (['smart_convert_runs', 'ai_request_logs', 'webhook_deliveries', 'marketplace_listings'] as $t) {
            $this->row(Schema::hasTable($t), "Table {$t}", 'php artisan migrate --force');
        }
        $this->row(Schema::hasColumn('marketplace_listings', 'payout_status'), 'marketplace_listings.payout_status', 'Run the 2026_11_03 migrations');
        $this->row(DB::table('merchants')->whereNotNull('paypal_connected_at')->exists(), 'A merchant has PayPal connected (required when driver=paypal)', 'Log in and use Connect PayPal once, before recording', warn: true);

        $this->section('Demo');
        $this->row((bool) config('luvupay.demo_simulator'), 'Demo simulator on (button + sandbox controls visible)', 'LUVUPAY_DEMO_SIMULATOR=true on the demo instance only');
        $this->line('  INFO  MFA is '.(config('luvupay.security.require_mfa') ? 'ON: enrol TOTP on the demo owner before recording' : 'off'));

        if ($this->option('live')) {
            $this->section('Live AI call');
            try {
                $p = $engine->propose((string) (DB::table('merchants')->value('id') ?? 'demo-check'), ['unused_days' => 10, 'unused_value_minor' => 3000]);
                $this->row($p['path'] === 'ai', "Model answered in {$p['ai']['latency_ms']} ms, {$p['ai']['tokens']} tokens, split {$p['store_credit_minor']}/{$p['charity_minor']}/{$p['fee_minor']}", 'Path was not AI');
            } catch (SmartConvertException $e) {
                $this->row(false, 'Live AI call', $e->getMessage());
            }
        }

        $this->newLine();
        $this->fails === 0 ? $this->info('GO: no blocking problems. WARN lines are worth fixing before you record.') : $this->error("NO-GO: {$this->fails} blocking problem(s).");

        return $this->fails === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function section(string $t): void
    {
        $this->newLine();
        $this->line("<options=bold>{$t}</>");
    }

    private function row(mixed $ok, string $label, string $fix = '', bool $warn = false): void
    {
        if ($ok) {
            $this->line("  <fg=green>PASS</>  {$label}");

            return;
        }
        if ($warn) {
            $this->line("  <fg=yellow>WARN</>  {$label}".($fix ? "  → {$fix}" : ''));

            return;
        }
        $this->fails++;
        $this->line("  <fg=red>FAIL</>  {$label}".($fix ? "  → {$fix}" : ''));
    }
}
