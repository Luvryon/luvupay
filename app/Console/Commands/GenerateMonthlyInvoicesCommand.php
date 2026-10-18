<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use App\Models\MonthlyInvoice;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateMonthlyInvoicesCommand extends Command
{
    protected $signature = 'luvi:generate-invoices {--period=}';
    protected $description = 'Generate SaaS monthly invoices for active merchants';

    public function handle(): int
    {
        $period = $this->option('period') ?: now()->format('Y-m');
        $prices = ['starter' => 0, 'growth' => 4900, 'enterprise' => 19900]; // minor USD

        $count = 0;
        Merchant::query()->where('is_active', true)->where('subscription_status', 'active')
            ->each(function (Merchant $m) use ($period, $prices, &$count) {
                $tier = $m->subscription_tier ?? 'starter';
                $amount = $prices[$tier] ?? 0;
                if ($amount <= 0) {
                    return;
                }
                MonthlyInvoice::query()->firstOrCreate(
                    ['merchant_id' => $m->id, 'period' => $period],
                    [
                        'id' => (string) Str::uuid(),
                        'tier' => $tier,
                        'amount_minor' => $amount,
                        'currency' => 'USD',
                        'status' => 'open',
                        'due_at' => now()->endOfMonth(),
                        'line_items' => [['desc' => "LuvuPay {$tier} plan", 'amount_minor' => $amount]],
                    ]
                );
                $count++;
            });
        $this->info("Invoices upserted: {$count} for {$period}");

        return self::SUCCESS;
    }
}
