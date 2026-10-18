<?php

namespace App\Console\Commands;

use App\Services\PayPal\PayPalClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SandboxCheckCommand extends Command
{
    protected $signature = 'luvupay:sandbox-check';

    protected $description = 'Verify local/sandbox readiness (DB, mail, payment driver, optional PayPal OAuth)';

    public function handle(): int
    {
        $ok = true;
        $driver = config('luvupay.payment.driver', env('LUVUPAY_PAYMENT_DRIVER'));
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

        foreach (['merchants', 'merchant_users', 'webhook_endpoints', 'transactions'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("[FAIL] Missing table: {$table} — run php artisan migrate");
                $ok = false;
            }
        }

        $mailer = config('mail.default');
        $this->line('[INFO] Mailer: '.$mailer);
        if ($mailer === 'log') {
            $this->warn('[WARN] MAIL_MAILER=log — verification codes go to storage/logs, not inbox');
        }

        if ($driver === 'mock') {
            $this->line('[OK] Mock driver — no PayPal credentials required');
        } elseif ($driver === 'paypal') {
            $mode = config('services.paypal.mode');
            $id = config('services.paypal.client_id');
            $secret = config('services.paypal.secret');
            $wh = config('services.paypal.webhook_id');
            $this->line('[INFO] PayPal mode: '.$mode);
            if ($mode !== 'sandbox') {
                $this->warn('[WARN] PAYPAL_MODE is not sandbox — use sandbox until go-live');
            }
            if (! $id || ! $secret) {
                $this->error('[FAIL] Set PAYPAL_CLIENT_ID and PAYPAL_SECRET');
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
            if (! $wh) {
                $this->warn('[WARN] PAYPAL_WEBHOOK_ID empty — ingress webhook verification will refuse events');
            }
        } else {
            $this->warn('[WARN] Unknown payment driver: '.$driver);
        }

        if (! config('luvupay.webhooks.allow_insecure_targets') && app()->environment('local')) {
            $this->warn('[WARN] LUVUPAY_ALLOW_INSECURE_WEBHOOK_TARGETS=false — local http webhooks may be blocked');
        }

        $this->newLine();
        $this->info($ok ? 'Sandbox check passed (with possible warnings).' : 'Sandbox check failed — fix items above.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
