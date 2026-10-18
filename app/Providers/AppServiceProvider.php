<?php

namespace App\Providers;

use App\Contracts\NebiusAIContract;
use App\Services\Nebius\NebiusClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NebiusAIContract::class, NebiusClient::class);
    }

    public function boot(): void
    {
        $this->assertSafeConfiguration();

        // Per-merchant API limit (falls back to IP before authentication). Override per merchant in merchants.rate_limit_per_minute.
        RateLimiter::for('merchant-api', function (Request $request) {
            $m = $request->attributes->get('merchant');
            $limit = (int) ($m?->rate_limit_per_minute ?: config('luvupay.security.default_rate_limit_per_minute', 300));

            return Limit::perMinute($limit)->by($m ? 'merchant:'.$m->getKey() : 'ip:'.$request->ip());
        });
    }

    /**
     * Fail at boot — not at 3am — when the environment could mix up sandbox and live money or run unsafe.
     */
    private function assertSafeConfiguration(): void
    {
        if ($this->app->runningUnitTests()) {
            return;
        }
        $env = $this->app->environment();
        $mode = (string) config('services.paypal.mode', 'sandbox');
        $driver = (string) config('luvupay.payment.driver', 'mock');

        if ($driver === 'paypal' && $mode === 'live' && $env !== 'production') {
            throw new RuntimeException('PAYPAL_MODE=live is only allowed when APP_ENV=production.');
        }
        if ($env === 'production') {
            if (config('app.debug')) {
                throw new RuntimeException('APP_DEBUG must be false in production.');
            }
            if ($driver === 'mock') {
                throw new RuntimeException('LUVUPAY_PAYMENT_DRIVER=mock is not allowed in production.');
            }
            if (! config('luvupay.audit.key') || str_starts_with((string) config('luvupay.audit.key'), 'change-me')) {
                throw new RuntimeException('AUDIT_HMAC_KEY must be set to a long random value in production.');
            }
        }
        if ($driver === 'paypal' && (! config('services.paypal.client_id') || ! config('services.paypal.secret') || ! config('services.paypal.webhook_id'))) {
            throw new RuntimeException('PayPal driver needs PAYPAL_CLIENT_ID, PAYPAL_SECRET and PAYPAL_WEBHOOK_ID.');
        }
    }
}
