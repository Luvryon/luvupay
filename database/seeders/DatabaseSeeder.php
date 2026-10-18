<?php

namespace Database\Seeders;

use App\Models\CharityWallet;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\PaypalPayoutRecipient;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction() && ! env('LUVUPAY_ALLOW_DEMO_SEED')) {
            throw new \RuntimeException('Refusing to seed demo data (known credentials) in production. Set LUVUPAY_ALLOW_DEMO_SEED=true only for a throwaway demo environment.');
        }

        $charity = CharityWallet::create([
            'id' => (string) Str::uuid(),
            'name' => 'LuvuPay Global Impact Fund',
            'payout_email' => 'donations@charity.example.org',
            'currency' => 'USD',
            'is_active' => true,
            'verification_status' => 'verified',
            'legal_name' => 'LuvuPay Global Impact Fund',
            'verified_at' => now(),
        ]);

        $keyData = Merchant::issueApiKey('live');

        $merchant = Merchant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Demo SaaS Merchant',
            'email' => 'owner@demomerchant.test',
            'api_key_prefix' => $keyData['prefix'],
            'api_key_hash' => $keyData['hash'],
            'publishable_key' => $keyData['publishable'],
            'allowed_origins' => ['http://localhost:3000', 'https://demomerchant.test'],
            'split_user_bps' => 3000,
            'split_charity_bps' => 2000,
            'split_merchant_bps' => 5000,
            'is_active' => true,
        ]);

        $user = MerchantUser::registerOwner($merchant, 'Demo Owner', 'owner@demomerchant.test', 'SecurePass1');
        $user->forceFill(['email_verified_at' => now()])->save();

        // Pre-activate charity payout allow-list entry for demo
        PaypalPayoutRecipient::create([
            'id' => (string) Str::uuid(),
            'merchant_id' => $merchant->id,
            'email' => 'donations@charity.example.org',
            'label' => 'Default charity',
            'purpose' => 'charity',
            'status' => 'active',
            'requested_at' => now(),
            'activates_at' => now(),
            'activated_at' => now(),
            'requested_by' => $user->id,
        ]);

        WebhookEndpoint::create([
            'id' => (string) Str::uuid(),
            'merchant_id' => $merchant->id,
            'url' => 'https://demomerchant.test/webhooks/luvupay',
            'secret' => 'whsec_'.Str::random(32),
            'events' => ['entitlement.transfer', 'subscription.cancelled', 'second_chance.triggered'],
            'is_active' => true,
        ]);

        $this->command?->info('Demo login: owner@demomerchant.test / SecurePass1 (already verified)');
        $this->call(DemoJudgeSeeder::class);
        $this->command?->info('Demo merchant API key (show ONCE): '.$keyData['key']);
        $this->command?->info('Publishable key: '.$keyData['publishable']);
        $this->command?->info('Merchant ID: '.$merchant->id);
        $this->command?->info('Charity wallet: '.$charity->id);
    }
}
