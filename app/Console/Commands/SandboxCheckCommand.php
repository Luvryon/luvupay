<?php

namespace App\Console\Commands;

use App\Services\PayPal\PayPalClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SandboxCheckCommand extends Command
{
    protected $signature = 'luvupay:sandbox-check';

    protected $description = 'Verify PayPal sandbox readiness and money-flow rules (subscribe, grace, resale, wallet)';

    public function handle(): int
    {
        $ok = true;
        $driver = (string) config('luvupay.payment.driver', env('LUVUPAY_PAYMENT_DRIVER', 'mock'));
        $this->info('Payment driver: '.$driver);
        $this->info('APP_ENV: '.app()->environment());
        $this->info('APP_URL: '.config('app.url'));

        try {
            DB::connection()->getPdo();
            $this->line('[OK] Database connection');
        } catch (\Throwable $e) {
            $this->error('[FAIL] Database: '.$e->getMessage());
            $ok = false;
        }

        foreach (['merchants', 'merchant_users', 'webhook_endpoints', 'transactions', 'subscriptions'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("[FAIL] Missing table: {$table} - run php artisan migrate");
                $ok = false;
            }
        }

        $mailer = config('mail.default');
        $this->line('[INFO] Mailer: '.$mailer);
        if ($mailer === 'log') {
            $this->warn('[WARN] MAIL_MAILER=log - verification codes go to storage/logs, not inbox');
        }

        if ($driver === 'mock') {
            $this->line('[OK] Mock driver - offline demo works without PayPal');
            $this->warn('[WARN] For real sandbox E2E set LUVUPAY_PAYMENT_DRIVER=paypal and fill sandbox credentials');
        } elseif ($driver === 'paypal') {
            $mode = (string) config('services.paypal.mode', 'sandbox');
            $id = (string) config('services.paypal.client_id', '');
            $secret = (string) config('services.paypal.secret', '');
            $wh = (string) config('services.paypal.webhook_id', '');
            $this->line('[INFO] PayPal mode: '.$mode);
            if ($mode !== 'sandbox') {
                $this->warn('[WARN] PAYPAL_MODE is not sandbox - keep sandbox until go-live');
            }
            if ($id === '' || $secret === '') {
                $this->error('[FAIL] Set PAYPAL_CLIENT_ID and PAYPAL_SECRET (or SANDBOX aliases)');
                $ok = false;
            } else {
                try {
                    $token = app(PayPalClient::class)->accessToken(true);
                    $this->line('[OK] PayPal OAuth token received ('.strlen($token).' chars)');
                } catch (\Throwable $e) {
                    $this->error('[FAIL] PayPal OAuth: '.$e->getMessage());
                    $ok = false;
                }
            }
            if ($wh === '') {
                $this->warn('[WARN] PAYPAL_WEBHOOK_ID empty - register '.rtrim((string) config('app.url'), '/').'/api/v1/webhooks/paypal');
            } else {
                $this->line('[OK] PAYPAL_WEBHOOK_ID is set');
            }

            $buyer = (string) config('services.paypal.sandbox_buyer_email', env('PAYPAL_SANDBOX_BUYER_EMAIL', ''));
            $biz = (string) config('services.paypal.sandbox_business_email', env('PAYPAL_SANDBOX_BUSINESS_EMAIL', ''));
            if ($buyer !== '') {
                $this->line('[OK] Sandbox buyer login email configured (browser popup tests)');
            } else {
                $this->warn('[WARN] Set PAYPAL_SANDBOX_BUYER_EMAIL / PASSWORD for popup login tests');
            }
            if ($biz !== '') {
                $this->line('[OK] Sandbox business login email configured');
            } else {
                $this->warn('[WARN] Set PAYPAL_SANDBOX_BUSINESS_EMAIL for receiving sandbox funds');
            }
        } else {
            $this->warn('[WARN] Unknown payment driver: '.$driver);
        }

        $this->newLine();
        $this->info('Money-flow rules (sandbox E2E)');
        $this->line('  1) Subscribe (PayPal approve) -> charge completed -> merchant wallet +amount');
        $this->line('  2) Grace period -> charge held (not in wallet yet)');
        $this->line('  3) Resale list -> charge still held; no fee taken from merchant charge');
        $this->line('  4) Resale sold -> charge completed (merchant keeps original); buyer pays seller+platform fee');
        $this->line('  5) Wallet = completed charges - refunds - charity - withdrawals (resale does not cut merchant)');

        $this->newLine();
        $this->info($ok ? 'Sandbox check passed (see warnings).' : 'Sandbox check failed - fix items above.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
