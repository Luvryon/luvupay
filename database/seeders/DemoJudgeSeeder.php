<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Judge-ready ledger rows so demo never starts empty.
 * Run: php artisan db:seed --class=DemoJudgeSeeder
 */
class DemoJudgeSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction() && ! env('LUVUPAY_ALLOW_DEMO_SEED')) {
            throw new \RuntimeException('Refusing to seed demo data (known credentials) in production. Set LUVUPAY_ALLOW_DEMO_SEED=true only for a throwaway demo environment.');
        }

        $merchant = DB::table('merchants')->orderBy('created_at')->first();
        if (! $merchant) {
            $this->command?->error('No merchant — run DatabaseSeeder first.');

            return;
        }

        $mid = $merchant->id;
        $now = now();
        $currency = $merchant->currency ?? 'USD';

        $rows = [
            [
                'lifecycle' => 'ACTIVE',
                'status' => 'completed',
                'email' => 'alex.active@demo.luvupay.local',
                'amount' => 2000,
                'trust' => 88,
                'churn' => 'low',
            ],
            [
                'lifecycle' => 'ACTIVE',
                'status' => 'completed',
                'email' => 'jordan.binge@demo.luvupay.local',
                'amount' => 2000,
                'trust' => 72,
                'churn' => 'medium',
            ],
            [
                'lifecycle' => 'GRACE_PERIOD',
                'status' => 'held',
                'email' => 'sam.forgot@demo.luvupay.local',
                'amount' => 2000,
                'trust' => 55,
                'churn' => 'high',
            ],
        ];

        foreach ($rows as $i => $r) {
            $id = (string) Str::uuid();
            DB::table('transactions')->insert([
                'id' => $id,
                'merchant_id' => $mid,
                'type' => 'charge',
                'status' => $r['status'],
                'idempotency_key' => 'judge-seed-'.$i.'-'.Str::random(6),
                'email' => $r['email'],
                'amount_minor' => $r['amount'],
                'currency' => $currency,
                'provider' => 'mock',
                'provider_reference' => 'JUDGE-'.strtoupper(Str::random(8)),
                'processed_at' => $now->copy()->subHours(3 - $i),
                'trust_score' => $r['trust'],
                'churn_risk' => $r['churn'],
                'metadata' => json_encode([
                    'demo' => true,
                    'lifecycle' => $r['lifecycle'],
                    'display_name' => explode('.', $r['email'])[0],
                ]),
                'created_at' => $now->copy()->subHours(3 - $i),
                'updated_at' => $now,
            ]);
        }

        // One failed webhook delivery for Retry Center
        if (DB::getSchemaBuilder()->hasTable('webhook_deliveries')) {
            DB::table('webhook_deliveries')->insert([
                'id' => (string) Str::uuid(),
                'merchant_id' => $mid,
                'event_type' => 'second_chance.window_opened',
                'payload' => json_encode(['seed' => true, 'note' => 'judge demo failed delivery']),
                'response_code' => 500,
                'attempts' => 2,
                'next_retry_at' => $now,
                'status' => 'failed',
                'last_error' => 'Simulated merchant endpoint error (500)',
                'target_url' => 'https://merchant.example.invalid/webhooks/luvupay',
                'delivered_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->command?->info('Judge demo rows seeded (3 transactions + 1 failed webhook delivery).');
    }
}
