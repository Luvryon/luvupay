<?php

namespace App\Console\Commands;

use App\DTOs\Payment\ProviderWebhookEvent;
use App\Enums\SubscriptionStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Merchant;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Services\Luvu\SecondChanceService;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class DemoSecondChanceE2ECommand extends Command
{
    protected $signature = 'demo:second-chance {--merchant=}';
    protected $description = 'Second Chance E2E with mock provider: prior charge → HELD renewal → trigger → execute split';

    public function handle(SecondChanceService $svc): int
    {
        $merchant = $this->option('merchant')
            ? Merchant::query()->findOrFail($this->option('merchant'))
            : Merchant::query()->first();
        if (! $merchant) {
            $this->error('No merchant. Run db:seed first.');
            return self::FAILURE;
        }

        $sub = Subscription::create([
            'id' => (string) Str::uuid(),
            'merchant_id' => $merchant->id,
            'merchant_user_id' => 'demo_user_sc',
            'email' => 'alice-sc@example.com',
            'provider' => 'mock',
            'provider_subscription_id' => 'I-MOCK-SC-'.Str::upper(Str::random(6)),
            'provider_plan_id' => 'plan_basic',
            'status' => SubscriptionStatus::Active,
            'amount_minor' => 2000,
            'currency' => 'USD',
            'current_period_start' => now()->subDays(30),
            'current_period_end' => now(),
            'next_billing_at' => now()->addDays(30),
        ]);

        Transaction::create([
            'id' => (string) Str::uuid(),
            'merchant_id' => $merchant->id,
            'subscription_id' => $sub->id,
            'type' => TransactionType::Charge,
            'status' => TransactionStatus::Settled,
            'idempotency_key' => 'charge:prior-'.$sub->id,
            'merchant_user_id' => $sub->merchant_user_id,
            'email' => $sub->email,
            'amount_minor' => 2000,
            'currency' => 'USD',
            'processed_at' => now()->subDays(30),
        ]);

        $event = new ProviderWebhookEvent(
            eventId: 'WH-SC-'.(string) Str::uuid(),
            type: 'PAYMENT.CAPTURE.COMPLETED',
            providerSubscriptionId: $sub->provider_subscription_id,
            providerCaptureId: 'CAP-SC-'.Str::upper(Str::random(8)),
            amount: Money::of(2000, 'USD'),
            customId: $sub->id,
        );

        $charge = $svc->registerCharge($sub->fresh(), $event);
        if (! $charge) {
            $this->error('registerCharge returned null');
            return self::FAILURE;
        }
        $this->info("1) Charge HELD: {$charge->id}");

        $tx = $svc->trigger($charge, 'demo_cli');
        $this->info("2) Triggered: {$tx->status->value}");

        $svc->execute($tx->id);
        $tx->refresh();
        $this->info("3) Executed: {$tx->status->value}");

        $children = Transaction::query()->where('parent_transaction_id', $tx->id)->get(['type', 'status', 'amount_minor']);
        $this->table(['type', 'status', 'amount_minor'], $children->map(fn ($c) => [
            is_object($c->type) ? $c->type->value : $c->type,
            is_object($c->status) ? $c->status->value : $c->status,
            $c->amount_minor,
        ]));

        return self::SUCCESS;
    }
}
