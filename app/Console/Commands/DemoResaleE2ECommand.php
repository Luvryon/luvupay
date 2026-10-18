<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Merchant;
use App\Models\Subscription;
use App\Services\Resale\ResaleService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class DemoResaleE2ECommand extends Command
{
    protected $signature = 'demo:resale {--merchant=}';
    protected $description = 'Resale E2E with mock: list → checkout → complete (+ entitlement job dispatch)';

    public function handle(ResaleService $resale): int
    {
        $merchant = $this->option('merchant')
            ? Merchant::query()->findOrFail($this->option('merchant'))
            : Merchant::query()->first();
        if (! $merchant) {
            $this->error('No merchant. Seed first.');
            return self::FAILURE;
        }

        $sub = Subscription::create([
            'id' => (string) Str::uuid(),
            'merchant_id' => $merchant->id,
            'merchant_user_id' => 'seller_demo',
            'email' => 'seller@example.com',
            'provider' => 'mock',
            'provider_subscription_id' => 'I-MOCK-RS-'.Str::upper(Str::random(6)),
            'status' => SubscriptionStatus::Active,
            'amount_minor' => 2000,
            'currency' => 'USD',
            'current_period_end' => now()->addDays(29),
            'next_billing_at' => now()->addDays(29),
        ]);

        $listing = $resale->list($merchant, $sub, 1000);
        $this->info('1) Listing '.$listing->id);

        $checkout = $resale->checkout(
            $listing,
            'buyer_demo',
            'buyer@example.com',
            'https://example.com/return',
            'https://example.com/cancel',
        );
        $this->info('2) Checkout order='.($checkout['order_id'] ?? '?'));

        $done = $resale->complete($listing->fresh(), $checkout['order_id']);
        $this->info('3) Complete status='.(is_object($done->status) ? $done->status->value : $done->status));
        $this->info('   entitlement='.(is_object($done->entitlement_status) ? $done->entitlement_status->value : $done->entitlement_status));

        return self::SUCCESS;
    }
}
